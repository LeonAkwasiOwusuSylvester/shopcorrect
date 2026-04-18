<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; // Added currency helper

/* --------------------------------------------------
   1. PREPARE DATA
   -------------------------------------------------- */
$vendorId = $_SESSION['user_id'];

// Get Vendor Table ID
$vStmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$vStmt->execute([$vendorId]);
$vendorData = $vStmt->fetch(PDO::FETCH_ASSOC);
if (!$vendorData) { header("Location: /shopcorrect/public/create-shop.php"); exit; }
$shopName = $vendorData['shop_name'];
$realVendorId = $vendorData['id'];

// Extract current active currency symbol for inputs
$fmt0 = formatPrice(0);
preg_match('/^[^\d]*/', $fmt0, $preMatch);
preg_match('/[^\d]*$/', $fmt0, $sufMatch);
$activeSymbol = trim($preMatch[0] . $sufMatch[0]); 
if(empty($activeSymbol)) $activeSymbol = '₵'; 

// Get Product
$productId = $_GET['id'] ?? null;
if (!$productId) { echo "<script>window.location.href='products.php';</script>"; exit; }

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
$stmt->execute([$productId, $realVendorId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { die("Product not found or access denied."); }

// Fetch Categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Fetch Existing Variants
$varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$varStmt->execute([$productId]);
$variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);
$jsVariants = json_encode($variants);

/* --------------------------------------------------
   2. ROBUST DATA PARSING (PHP -> JS)
   -------------------------------------------------- */

// Specs (Handle JSON string or empty)
$specsArr = [];
if (!empty($product['specifications'])) {
    $decoded = json_decode($product['specifications'], true);
    if (is_array($decoded)) {
        $specsArr = $decoded;
    }
}
$jsSpecs = json_encode($specsArr);

function safeVal($array, $key, $default = '') {
    return isset($array[$key]) ? htmlspecialchars($array[$key]) : $default;
}

// --------------------------------------------------
// 3. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Card & Form Styles */
    .card-modern { background: #fff; border: 1px solid var(--card-border); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); margin-bottom: 1.5rem; overflow: hidden; }
    .card-header-modern { padding: 1.25rem 1.5rem; background: #fff; border-bottom: 1px solid var(--card-border); display: flex; justify-content: space-between; align-items: center; }
    .section-title { font-size: 1rem; font-weight: 700; color: var(--primary-accent); margin: 0; display: flex; align-items: center; gap: 10px; }
    .card-body-modern { padding: 1.5rem; }
    .form-label { font-size: 0.85rem; font-weight: 700; color: var(--primary-accent); margin-bottom: 0.5rem; display: block; }
    .form-control, .form-select { border: 1px solid var(--card-border); border-radius: 10px; padding: 0.7rem 1rem; font-size: 0.95rem; color: var(--primary-accent); transition: all 0.2s; background-color: #F8FAFC; }
    .form-control:focus, .form-select:focus { background-color: #fff; border-color: var(--primary-accent); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.05); outline: none; }

    /* Dynamic Rows */
    .dynamic-row { background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 12px; border-radius: 10px; margin-bottom: 10px; transition: 0.2s; }
    .dynamic-row:hover { border-color: #3B82F6; background: #F0F9FF; }
    .btn-add-row { font-size: 0.85rem; font-weight: 700; color: #3B82F6; cursor: pointer; background: none; border: none; padding: 0; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; }
    .btn-remove-row { color: #DC2626; background: #fff; border: 1px solid #FECACA; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; flex-shrink: 0; }
    .btn-remove-row:hover { background: #DC2626; color: white; border-color: #DC2626; }

    .btn-submit { background: var(--primary-accent); color: #fff; font-weight: 700; padding: 0.85rem 2.5rem; border-radius: 100px; border: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(11, 36, 71, 0.2); }
    .btn-submit:hover { background: #1e3a8a; transform: translateY(-2px); }

    .upload-zone { border: 2px dashed #CBD5E1; border-radius: 12px; background: #F8FAFC; padding: 2rem 1rem; text-align: center; position: relative; cursor: pointer; }
    .upload-zone:hover { background: #F0F9FF; border-color: #3B82F6; }
    .upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .preview-item { width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 1px solid var(--card-border); background: white; display: flex; align-items: center; justify-content: center; }
    .preview-item img { width: 100%; height: 100%; object-fit: contain; }
</style>

<div class="container-fluid px-4 py-4">
    
    <form method="POST" action="../../routes/vendor.php" enctype="multipart/form-data" id="productForm">
        <input type="hidden" name="action" value="update_product">
        <input type="hidden" name="product_id" value="<?= safeVal($product, 'id') ?>">
        
        <input type="hidden" name="specifications" id="finalSpecs">
        <input type="hidden" name="discount_percent" id="finalDiscount">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
            <div>
                <a href="products.php" class="text-decoration-none text-muted small mb-1 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold text-dark mb-0">Product Details</h4>
                    <span class="badge bg-light text-secondary border fw-normal">ID: #<?= safeVal($product, 'id') ?></span>
                </div>
            </div>
            
            <button type="button" class="btn btn-submit" id="submitBtn">
                <span id="btnText"><i class="bi bi-check-lg"></i> Update Product</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
            </button>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                
                <div class="card-modern">
                    <div class="card-header-modern"><h5 class="section-title"><i class="bi bi-pencil-square"></i> General Information</h5></div>
                    <div class="card-body-modern">
                        <div class="mb-4">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="name" class="form-control" value="<?= safeVal($product, 'name') ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" id="descInput" class="form-control" rows="5" required><?= safeVal($product, 'description') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern"><h5 class="section-title"><i class="bi bi-images"></i> Media</h5></div>
                    <div class="card-body-modern">
                        <div class="row align-items-center mb-3">
                            <div class="col-auto">
                                <label class="form-label small text-muted">Current</label>
                                <div class="preview-item">
                                    <?php if(!empty($product['image'])): ?>
                                        <img src="../../public/uploads/products/<?= safeVal($product, 'image') ?>" alt="Current">
                                    <?php else: ?>
                                        <div class="text-muted small">No Image</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col">
                                <div class="upload-zone py-3">
                                    <input type="file" name="image" id="fileInput" accept="image/*">
                                    <h6 class="fw-bold text-dark mb-0">Replace Image</h6>
                                    <div class="small text-muted">Click or Drag new file here</div>
                                </div>
                            </div>
                        </div>
                        <div id="previewContainer"></div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern"><h5 class="section-title"><i class="bi bi-sliders2"></i> Attributes & Specs</h5></div>
                    <div class="card-body-modern">
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <label class="form-label mb-0">Product Variations</label>
                                    <div class="form-text text-muted small">Update colors, sizes, and their specific prices. Leave price blank to use the regular price.</div>
                                </div>
                                <button type="button" class="btn-add-row" onclick="addVariantRow()"><i class="bi bi-plus-circle"></i> Add Variant</button>
                            </div>
                            <div id="variantContainer"></div>
                            <div class="text-muted small fst-italic mt-2" id="noVariantsMsg" style="display:none;">No variations added.</div>
                        </div>
                        
                        <hr class="my-4" style="border-color: var(--card-border);">

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Specifications</label>
                                <button type="button" class="btn-add-row" onclick="addSpecRow()"><i class="bi bi-plus-circle"></i> Add Spec</button>
                            </div>
                            <div id="specContainer"></div>
                            <div class="text-muted small fst-italic mt-2" id="noSpecsMsg" style="display:none;">No specifications added.</div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="card-modern">
                    <div class="card-header-modern"><h5 class="section-title"><i class="bi bi-folder2-open"></i> Organization</h5></div>
                    <div class="card-body-modern">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="categorySelect" class="form-select" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($c['id'] == $product['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($product['status']=='active')?'selected':'' ?>>Active (Visible)</option>
                                <option value="inactive" <?= ($product['status']=='inactive')?'selected':'' ?>>Inactive (Hidden)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-building-up"></i> Fulfillment</h5>
                    </div>
                    <div class="card-body-modern">
                        <label class="form-label">How is this item stored?</label>
                        <select name="fulfillment_type" id="fulfillmentSelect" class="form-select mb-3" onchange="toggleWarehouseCountry()" required>
                            <option value="vendor" <?= (safeVal($product, 'fulfillment_type', 'vendor') === 'vendor') ? 'selected' : '' ?>>Shipped by Me (Default)</option>
                            <option value="shopcorrect" <?= (safeVal($product, 'fulfillment_type') === 'shopcorrect') ? 'selected' : '' ?>>Fulfilled by ShopCorrect</option>
                        </select>

                        <div id="warehouseCountryDiv" class="<?= (safeVal($product, 'fulfillment_type') === 'shopcorrect') ? '' : 'd-none' ?>">
                            <label class="form-label">Warehouse Location</label>
                            <select name="warehouse_country" id="warehouseCountry" class="form-select">
                                <option value="" disabled <?= empty($product['warehouse_country']) ? 'selected' : '' ?>>Select Warehouse...</option>
                                <optgroup label="Africa Warehouses">
                                    <option value="Ghana" <?= (safeVal($product, 'warehouse_country') === 'Ghana') ? 'selected' : '' ?>>ShopCorrect Hub - Ghana</option>
                                    <option value="Nigeria" <?= (safeVal($product, 'warehouse_country') === 'Nigeria') ? 'selected' : '' ?>>ShopCorrect Hub - Nigeria</option>
                                    <option value="Cote d'Ivoire" <?= (safeVal($product, 'warehouse_country') === "Cote d'Ivoire") ? 'selected' : '' ?>>ShopCorrect Hub - Côte d'Ivoire</option>
                                    <option value="South Africa" <?= (safeVal($product, 'warehouse_country') === 'South Africa') ? 'selected' : '' ?>>ShopCorrect Hub - South Africa</option>
                                    <option value="Kenya" <?= (safeVal($product, 'warehouse_country') === 'Kenya') ? 'selected' : '' ?>>ShopCorrect Hub - Kenya</option>
                                    <option value="Togo" <?= (safeVal($product, 'warehouse_country') === 'Togo') ? 'selected' : '' ?>>ShopCorrect Hub - Togo</option>
                                </optgroup>
                                <optgroup label="International Warehouses">
                                    <option value="United Kingdom" <?= (safeVal($product, 'warehouse_country') === 'United Kingdom') ? 'selected' : '' ?>>ShopCorrect Hub - United Kingdom</option>
                                    <option value="United States" <?= (safeVal($product, 'warehouse_country') === 'United States') ? 'selected' : '' ?>>ShopCorrect Hub - United States</option>
                                    <option value="Canada" <?= (safeVal($product, 'warehouse_country') === 'Canada') ? 'selected' : '' ?>>ShopCorrect Hub - Canada</option>
                                    <option value="Germany" <?= (safeVal($product, 'warehouse_country') === 'Germany') ? 'selected' : '' ?>>ShopCorrect Hub - Germany</option>
                                    <option value="China" <?= (safeVal($product, 'warehouse_country') === 'China') ? 'selected' : '' ?>>ShopCorrect Hub - China</option>
                                    <option value="Spain" <?= (safeVal($product, 'warehouse_country') === 'Spain') ? 'selected' : '' ?>>ShopCorrect Hub - Spain</option>
                                </optgroup>
                            </select>
                            <div class="form-text text-muted small mt-1">
                                <i class="bi bi-info-circle"></i> Selecting this enables Cash on Delivery for buyers in this specific country.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern"><h5 class="section-title"><i class="bi bi-tag"></i> Pricing</h5></div>
                    <div class="card-body-modern">
                        <div class="mb-3">
                            <label class="form-label">Regular Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted"><?= $activeSymbol ?></span>
                                <input type="number" step="0.01" min="0" id="regPrice" name="price" class="form-control border-start-0 ps-0" value="<?= safeVal($product, 'price') ?>" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Sale Price (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted"><?= $activeSymbol ?></span>
                                <input type="number" step="0.01" min="0" id="salePrice" name="sale_price" class="form-control border-start-0 ps-0" value="<?= safeVal($product, 'sale_price') ?>">
                            </div>
                            <div class="form-text text-muted small mt-1">Leave blank if not on sale.</div>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern"><h5 class="section-title"><i class="bi bi-box-seam"></i> Inventory & Shipping</h5></div>
                    <div class="card-body-modern">
                        <div class="mb-3">
                            <label class="form-label">Base Stock <small class="text-muted fw-normal">(If no variants added)</small></label>
                            <input type="number" name="stock" class="form-control" value="<?= safeVal($product, 'stock') ?>" min="0" required>
                        </div>
                        
                        <div>
                            <label class="form-label">Product Weight</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="weight" class="form-control border-end-0" placeholder="0.00" value="<?= safeVal($product, 'weight', '0.00') ?>" min="0" required>
                                <span class="input-group-text bg-white border-start-0 fw-bold text-muted">kg</span>
                            </div>
                            <div class="form-text text-muted small mt-1">Required for accurate checkout delivery rates.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

</div> 

<script>
    /* --- FULFILLMENT TOGGLE LOGIC --- */
    function toggleWarehouseCountry() {
        const select = document.getElementById('fulfillmentSelect');
        const warehouseDiv = document.getElementById('warehouseCountryDiv');
        const warehouseInput = document.getElementById('warehouseCountry');

        if (select.value === 'shopcorrect') {
            warehouseDiv.classList.remove('d-none');
            warehouseInput.setAttribute('required', 'required');
        } else {
            warehouseDiv.classList.add('d-none');
            warehouseInput.removeAttribute('required');
            warehouseInput.value = ''; // Reset if hidden
        }
    }

    /* -----------------------------------------------------------
       GLOBAL FUNCTIONS (For Buttons to Work)
    ----------------------------------------------------------- */
    
    // Add Variant Row (Color, Size, Price, Stock)
    window.addVariantRow = function(vColor = '', vSize = '', vPrice = '', vStock = '') {
        const container = document.getElementById('variantContainer');
        const noMsg = document.getElementById('noVariantsMsg');
        if(noMsg) noMsg.style.display = 'none';

        const div = document.createElement('div');
        div.className = 'dynamic-row row g-2 align-items-center mb-2';
        div.innerHTML = `
            <div class="col-12 col-md-3">
                <input type="text" name="variant_color[]" class="form-control form-control-sm" placeholder="Color (e.g. Rose Gold)" value="${vColor.replace(/"/g, '&quot;')}">
            </div>
            <div class="col-12 col-md-3">
                <input type="text" name="variant_size[]" class="form-control form-control-sm" placeholder="Size (e.g. XL)" value="${vSize.replace(/"/g, '&quot;')}">
            </div>
            <div class="col-12 col-md-3">
                <input type="number" step="0.01" name="variant_price[]" class="form-control form-control-sm" placeholder="Price (<?= $activeSymbol ?>)" value="${vPrice}">
            </div>
            <div class="col-10 col-md-2">
                <input type="number" name="variant_stock[]" class="form-control form-control-sm" placeholder="Stock Qty" value="${vStock}">
            </div>
            <div class="col-2 col-md-1 text-end">
                <button type="button" class="btn-remove-row w-100" onclick="this.closest('.row').remove()"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    };

    // Add Spec Row
    window.addSpecRow = function(key = '', val = '') {
        const container = document.getElementById('specContainer');
        const noMsg = document.getElementById('noSpecsMsg');
        if(noMsg) noMsg.style.display = 'none';

        const div = document.createElement('div');
        div.className = 'dynamic-row row g-2 align-items-center mb-2';
        div.innerHTML = `
            <div class="col-5">
                <input type="text" class="spec-key-input form-control form-control-sm" placeholder="Key (e.g. RAM)" value="${key.replace(/"/g, '&quot;')}">
            </div>
            <div class="col-6">
                <input type="text" class="spec-val-input form-control form-control-sm" placeholder="Value (e.g. 16GB)" value="${val.replace(/"/g, '&quot;')}">
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn-remove-row w-100" onclick="this.closest('.row').remove()"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    };

    /* -----------------------------------------------------------
       INITIALIZATION & BULLETPROOF SUBMISSION LOGIC
    ----------------------------------------------------------- */
    document.addEventListener("DOMContentLoaded", function() {

        // Ensure fulfillment toggle state is correct on load
        toggleWarehouseCountry();

        // 0. BLOCK ACCIDENTAL 'ENTER' KEY SUBMISSIONS IN TEXT FIELDS
        document.getElementById('productForm').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type === 'text') {
                e.preventDefault(); 
            }
        });

        // 1. LOAD SAVED VARIANTS
        const existingVariants = <?= $jsVariants ?>;
        if (Array.isArray(existingVariants) && existingVariants.length > 0) {
            existingVariants.forEach(v => {
                addVariantRow(v.color || '', v.size || '', v.price || '', v.stock || '');
            });
        } else {
            document.getElementById('noVariantsMsg').style.display = 'block';
        }

        // 2. LOAD SAVED SPECS
        const existingSpecs = <?= $jsSpecs ?>;
        if (existingSpecs && typeof existingSpecs === 'object' && Object.keys(existingSpecs).length > 0) {
            for (const [key, value] of Object.entries(existingSpecs)) {
                addSpecRow(key, value);
            }
        } else {
            document.getElementById('noSpecsMsg').style.display = 'block';
        }

        // 3. IMAGE PREVIEW
        const fileIn = document.getElementById('fileInput');
        const prevCont = document.getElementById('previewContainer');
        fileIn.addEventListener('change', function() {
            prevCont.innerHTML = '';
            if(this.files && this.files[0]){
                const reader = new FileReader();
                reader.onload = function(e) {
                    prevCont.innerHTML = `<div class="preview-item mt-2"><img src="${e.target.result}"></div>`;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // 4. BULLETPROOF SUBMIT HANDLER (Intercepts Button Click)
        document.getElementById('submitBtn').addEventListener('click', function(e) {
            e.preventDefault(); // Stop any early browser behaviors

            try {
                const form = document.getElementById('productForm');
                
                // --- A. Price Validation & Discount Calculation ---
                let pReg = parseFloat(document.getElementById('regPrice').value) || 0;
                let pSale = parseFloat(document.getElementById('salePrice').value) || 0;
                let calculatedDiscount = 0;

                if (pSale > 0) {
                    if (pSale >= pReg) {
                        alert("Error: Sale Price cannot be higher than or equal to the Regular Price.");
                        return; // Stop function completely
                    }
                    calculatedDiscount = Math.round(((pReg - pSale) / pReg) * 100);
                }
                document.getElementById('finalDiscount').value = calculatedDiscount;

                // --- B. Sync Specs (JSON) ---
                const specKeys = document.querySelectorAll('.spec-key-input');
                const specVals = document.querySelectorAll('.spec-val-input');
                let specsData = {};
                let hasSpecs = false;

                specKeys.forEach((keyInput, index) => {
                    let k = keyInput.value.trim();
                    let v = specVals[index].value.trim();
                    if(k !== '' && v !== '') {
                        specsData[k] = v;
                        hasSpecs = true;
                    }
                });
                document.getElementById('finalSpecs').value = hasSpecs ? JSON.stringify(specsData) : '';

                // --- C. Trigger Loader & Force Submit ---
                document.getElementById('btnText').classList.add('d-none');
                document.getElementById('btnSpinner').classList.remove('d-none');
                this.style.pointerEvents = 'none';

                // Programmatically submit the form ONLY after packaging is 100% complete
                form.submit();

            } catch (err) {
                console.error("Submission Error: ", err);
                alert("Something went wrong while packaging your data. Please check your inputs.");
            }
        });

    });
</script>

<?php
// --------------------------------------------------
// 4. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>