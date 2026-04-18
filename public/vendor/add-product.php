<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; 

// Extract current active currency symbol for inputs
$fmt0 = formatPrice(0);
preg_match('/^[^\d]*/', $fmt0, $preMatch);
preg_match('/[^\d]*$/', $fmt0, $sufMatch);
$activeSymbol = trim($preMatch[0] . $sufMatch[0]); 
if(empty($activeSymbol)) $activeSymbol = '₵'; 

// 1. Fetch Vendor Details for Sidebar/Nav
$stmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: /shopcorrect/public/create-shop.php");
    exit;
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

// 2. Fetch categories for dropdown
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// 3. Include Header
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* --- FORM STYLES --- */
    .card-modern {
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: 16px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .card-header-modern {
        padding: 1.25rem 1.5rem;
        background: #fff;
        border-bottom: 1px solid var(--card-border);
        display: flex; justify-content: space-between; align-items: center;
    }

    .section-title { font-size: 1rem; font-weight: 700; color: var(--primary-accent); margin: 0; display: flex; align-items: center; gap: 10px; }
    .section-title i { color: #3B82F6; }
    .card-body-modern { padding: 1.5rem; }

    /* Forms & Inputs */
    .form-label { font-size: 0.85rem; font-weight: 700; color: var(--primary-accent); margin-bottom: 0.5rem; display: block; }
    .input-wrapper { position: relative; }
    .form-control, .form-select {
        border: 1px solid var(--card-border); border-radius: 10px; padding: 0.7rem 1rem;
        font-size: 0.95rem; color: var(--primary-accent); transition: all 0.2s; background-color: #F8FAFC;
    }
    .form-control:focus, .form-select:focus { background-color: #fff; border-color: var(--primary-accent); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.05); outline: none; }

    /* Validation Icon */
    .validation-icon {
        position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
        color: #10B981; font-size: 1.2rem; display: none;
        animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    textarea ~ .validation-icon { top: 20px; transform: none; }
    @keyframes popIn { 0% { transform: scale(0) translateY(-50%); opacity: 0; } 100% { transform: scale(1) translateY(-50%); opacity: 1; } }

    /* Image Upload */
    .upload-zone {
        border: 2px dashed #CBD5E1; border-radius: 12px; background: #F8FAFC;
        padding: 3rem 1rem; text-align: center; position: relative; cursor: pointer; transition: all 0.2s;
    }
    .upload-zone:hover { background: #F0F9FF; border-color: #3B82F6; color: #3B82F6; }
    .upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 20px; }
    .preview-item { aspect-ratio: 1; border-radius: 10px; border: 1px solid var(--card-border); position: relative; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; }
    .btn-rm-img {
        position: absolute; top: 4px; right: 4px; background: rgba(255,255,255,0.95); color: #DC2626;
        border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Premium Dynamic Rows */
    .variant-card { background: #fff; border: 1px solid #cbd5e1; padding: 18px; border-radius: 12px; margin-bottom: 15px; transition: 0.2s; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .variant-card:hover { border-color: #3B82F6; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08); }
    .variant-label-sm { font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .dynamic-row { background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 12px; border-radius: 10px; margin-bottom: 10px; transition: 0.2s; }
    .dynamic-row:hover { border-color: #3B82F6; background: #F0F9FF; }

    .btn-add-row { font-size: 0.85rem; font-weight: 700; color: #fff; background: var(--primary-accent); cursor: pointer; border: none; padding: 6px 16px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-add-row:hover { background: #1e3a8a; transform: translateY(-1px); }
    
    .btn-remove-row { color: #DC2626; background: #fff; border: 1px solid #FECACA; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer;}
    .btn-remove-row:hover { background: #DC2626; color: white; border-color: #DC2626; }

    /* Submit Button */
    .btn-submit {
        background: var(--primary-accent); color: #fff; font-weight: 700;
        padding: 0.85rem 2.5rem; border-radius: 100px; border: none; transition: 0.2s;
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 12px rgba(11, 36, 71, 0.2);
    }
    .btn-submit:hover { background: #1e3a8a; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(11, 36, 71, 0.3); }

    .spec-hint { font-size: 0.75rem; color: #3B82F6; margin-top: 4px; display: none; }
</style>

<div class="container-fluid px-4 py-4">
    
    <form method="POST" action="../../routes/vendor.php" enctype="multipart/form-data" id="productForm">
        
        <input type="hidden" name="add_product" value="1">
        <input type="hidden" name="specifications" id="finalSpecs">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
            <div>
                <a href="products.php" class="text-decoration-none text-muted small mb-1 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
                <h4 class="fw-bold text-dark mb-0">Product Details</h4>
            </div>
            <button type="submit" class="btn btn-submit" id="submitBtn">
                <span id="btnText"><i class="bi bi-rocket-takeoff"></i> Publish Product</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
            </button>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-pencil-square"></i> General Information</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="mb-4">
                            <label class="form-label">Product Name</label>
                            <div class="input-wrapper">
                                <input type="text" name="name" class="form-control" placeholder="e.g. Samsung Galaxy S24 Ultra" required>
                                <i class="bi bi-check-circle-fill validation-icon"></i>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <div class="input-wrapper">
                                <textarea name="description" id="descInput" class="form-control" rows="5" placeholder="Highlight key features, condition, and selling points..." required></textarea>
                                <i class="bi bi-check-circle-fill validation-icon"></i>
                            </div>
                            <div class="form-text small text-muted">
                                <i class="bi bi-magic"></i> Tip: Press <b>Enter</b> after a list item (starting with -) to automatically create a list.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-images"></i> Media Gallery</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="upload-zone">
                            <input type="file" name="images[]" id="fileInput" multiple accept="image/*" required>
                            <div class="mb-3">
                                <span class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-cloud-arrow-up fs-3 text-primary"></i>
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark">Click or Drag images here</h6>
                            <div class="small text-muted">Upload high-quality images (Max 10MB each)</div>
                        </div>
                        <div id="previewContainer" class="preview-grid"></div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-sliders2"></i> Attributes & Specs</h5>
                    </div>
                    <div class="card-body-modern">
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <label class="form-label mb-0">Product Variations</label>
                                    <div class="form-text text-muted small">Add colors, sizes, and specific prices. Leave price blank to use the regular base price.</div>
                                </div>
                                <button type="button" class="btn-add-row" onclick="addVariantRow()"><i class="bi bi-plus-circle"></i> Add Variant</button>
                            </div>
                            <div id="variantContainer">
                                </div>
                        </div>
                        
                        <hr class="my-4" style="border-color: var(--card-border);">

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <label class="form-label mb-0">Specifications</label>
                                    <div id="specHint" class="spec-hint"><i class="bi bi-lightbulb"></i> Suggested: Processor, RAM, Screen Size</div>
                                </div>
                                <button type="button" class="btn-add-row" onclick="addSpecRow()"><i class="bi bi-plus-circle"></i> Add Spec</button>
                            </div>
                            <div id="specContainer"></div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="col-lg-4">

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-qr-code-scan"></i> Product Verification</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-shield-check text-success fs-1"></i>
                            <div>
                                <h6 class="mb-1 fw-bold">ShopCorrect Secured</h6>
                                <p class="mb-0 small text-muted">A unique, scannable QR code will be automatically generated when you publish this product to guarantee authenticity for buyers.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-folder2-open"></i> Organization</h5>
                    </div>
                    <div class="card-body-modern">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="categorySelect" class="form-select" required>
                            <option value="" disabled selected>Select Category...</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" data-name="<?= strtolower($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-building-up"></i> Fulfillment</h5>
                    </div>
                    <div class="card-body-modern">
                        <label class="form-label">How is this item stored?</label>
                        <select name="fulfillment_type" id="fulfillmentSelect" class="form-select mb-3" onchange="toggleWarehouseCountry()" required>
                            <option value="vendor" selected>Shipped by Me (Default)</option>
                            <option value="shopcorrect">Fulfilled by ShopCorrect</option>
                        </select>

                        <div id="warehouseCountryDiv" class="d-none">
                            <label class="form-label">Warehouse Location</label>
                            <select name="warehouse_country" id="warehouseCountry" class="form-select">
                                <option value="" disabled selected>Select Warehouse...</option>
                                <optgroup label="Africa Warehouses">
                                    <option value="Ghana">ShopCorrect Hub - Ghana</option>
                                    <option value="Nigeria">ShopCorrect Hub - Nigeria</option>
                                    <option value="Cote d'Ivoire">ShopCorrect Hub - Côte d'Ivoire</option>
                                    <option value="South Africa">ShopCorrect Hub - South Africa</option>
                                    <option value="Kenya">ShopCorrect Hub - Kenya</option>
                                    <option value="Togo">ShopCorrect Hub - Togo</option>
                                </optgroup>
                                <optgroup label="International Warehouses">
                                    <option value="United Kingdom">ShopCorrect Hub - United Kingdom</option>
                                    <option value="United States">ShopCorrect Hub - United States</option>
                                    <option value="Canada">ShopCorrect Hub - Canada</option>
                                    <option value="Germany">ShopCorrect Hub - Germany</option>
                                    <option value="China">ShopCorrect Hub - China</option>
                                    <option value="Spain">ShopCorrect Hub - Spain</option>
                                </optgroup>
                            </select>
                            <div class="form-text text-muted small mt-1">
                                <i class="bi bi-info-circle"></i> Selecting this enables Cash on Delivery for buyers in this specific country.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-tag"></i> Pricing</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="mb-3">
                            <label class="form-label">Regular Base Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted"><?= $activeSymbol ?></span>
                                <input type="number" step="0.01" name="original_price" class="form-control border-start-0 ps-0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Sale Price <small class="text-muted fw-normal">(Optional)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted"><?= $activeSymbol ?></span>
                                <input type="number" step="0.01" name="discount_price" class="form-control border-start-0 ps-0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="section-title"><i class="bi bi-box-seam"></i> Inventory & Shipping</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="mb-3">
                            <label class="form-label">Base Stock <small class="text-muted fw-normal">(If no variants added)</small></label>
                            <input type="number" name="stock" class="form-control" value="1" min="0" required>
                            <div class="form-text text-muted small mt-1">
                                <i class="bi bi-info-circle"></i> Products with 0 stock will be marked as "Out of Stock".
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">Product Weight</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="weight" class="form-control border-end-0" placeholder="0.00" min="0" required>
                                <span class="input-group-text bg-white border-start-0 fw-bold text-muted">kg</span>
                            </div>
                            <div class="form-text text-muted small mt-1">
                                <i class="bi bi-truck"></i> Required for accurate checkout delivery rates.
                            </div>
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
            warehouseInput.value = ''; 
        }
    }

    /* --- FORM LOGIC --- */
    document.addEventListener("DOMContentLoaded", function() {

        // 0. Auto-Checkmark
        const inputs = document.querySelectorAll('input[type="text"], textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const wrapper = this.closest('.input-wrapper');
                if(wrapper) {
                    const icon = wrapper.querySelector('.validation-icon');
                    if(icon) {
                        if(this.value.trim().length > 3) icon.style.display = 'block';
                        else icon.style.display = 'none';
                    }
                }
            });
        });

        // 0.5 Auto-List Helper
        const descInput = document.getElementById('descInput');
        descInput.addEventListener('keydown', function(e) {
            if(e.key === 'Enter') {
                const cursor = this.selectionStart;
                const text = this.value;
                const currentLineStart = text.lastIndexOf('\n', cursor - 1) + 1;
                const currentLine = text.substring(currentLineStart, cursor);
                if(currentLine.trim().startsWith('-')) {
                    e.preventDefault();
                    const afterCursor = text.substring(cursor);
                    this.value = text.substring(0, cursor) + '\n- ' + afterCursor;
                    this.selectionStart = this.selectionEnd = cursor + 3; 
                }
            }
        });

        // 1. Smart Spec Suggestions
        const catSelect = document.getElementById('categorySelect');
        const specHint = document.getElementById('specHint');
        const hints = {
            'comput': 'Suggested: Processor, RAM, Storage, Screen Size',
            'phone': 'Suggested: Storage, RAM, Battery, Camera',
            'fashion': 'Suggested: Material, Fit, Care Instructions',
            'shirt': 'Suggested: Material, Fit, Care Instructions',
            'beauty': 'Suggested: Skin Type, Volume, Ingredients',
            'health': 'Suggested: Dosage, Volume, Expiry',
            'appliance': 'Suggested: Voltage, Power (Watts), Warranty',
            'home': 'Suggested: Dimensions, Material, Assembly',
            'game': 'Suggested: Platform, Genre, Multiplayer',
            'electronic': 'Suggested: Power, Connectivity, Warranty'
        };

        catSelect.addEventListener('change', function() {
            const selectedName = this.options[this.selectedIndex].getAttribute('data-name') || '';
            let foundHint = false;
            for (const [key, value] of Object.entries(hints)) {
                if (selectedName.includes(key)) {
                    specHint.innerHTML = `<i class="bi bi-lightbulb"></i> ${value}`;
                    specHint.style.display = 'block';
                    foundHint = true;
                    break;
                }
            }
            if(!foundHint) specHint.style.display = 'none';
        });

        // 3. Premium Dynamic Variants
        window.addVariantRow = function() {
            const container = document.getElementById('variantContainer');
            const div = document.createElement('div');
            div.className = 'variant-card';
            div.innerHTML = `
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <div class="variant-label-sm">Color / Detail</div>
                        <input type="text" name="variant_color[]" class="form-control" placeholder="e.g. Rose Gold">
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="variant-label-sm">Size / Option</div>
                        <input type="text" name="variant_size[]" class="form-control" placeholder="e.g. 42mm">
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="variant-label-sm">Custom Price</div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><?= $activeSymbol ?></span>
                            <input type="number" step="0.01" name="variant_price[]" class="form-control border-start-0 ps-0" placeholder="Base Price">
                        </div>
                    </div>
                    <div class="col-10 col-md-2">
                        <div class="variant-label-sm">Qty. Stock</div>
                        <input type="number" name="variant_stock[]" class="form-control" placeholder="0" value="1" required>
                    </div>
                    <div class="col-2 col-md-1 text-end">
                        <button type="button" class="btn-remove-row w-100" onclick="this.closest('.variant-card').remove()" title="Delete Variant">
                            <i class="bi bi-trash fs-5"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }
        
        addVariantRow(); // Add an empty row by default

        // 4. Dynamic Specs
        window.addSpecRow = function() {
            const container = document.getElementById('specContainer');
            const div = document.createElement('div');
            div.className = 'dynamic-row row g-2 align-items-center';
            div.innerHTML = `
                <div class="col-5"><input type="text" name="spec_keys[]" class="spec-key-input form-control" placeholder="Key (e.g. Brand)"></div>
                <div class="col-6"><input type="text" name="spec_values[]" class="spec-val-input form-control" placeholder="Value (e.g. Samsung)"></div>
                <div class="col-1 text-end"><button type="button" class="btn-remove-row w-100" style="height: 38px;" onclick="this.closest('.row').remove()"><i class="bi bi-trash"></i></button></div>
            `;
            container.appendChild(div);
        }
        
        addSpecRow(); // Execute it once on load to show a default row

        // 5. Image Preview
        const dt = new DataTransfer();
        const fileIn = document.getElementById('fileInput');
        const prevCont = document.getElementById('previewContainer');
        const MAX_SIZE = 10 * 1024 * 1024; // 10MB

        fileIn.addEventListener('change', function() {
            for(let i = 0; i < this.files.length; i++){
                const file = this.files[i];
                if(file.size > MAX_SIZE) {
                    alert(`File "${file.name}" is too large! Max size is 10MB.`);
                    continue; 
                }
                dt.items.add(file);
            }
            this.files = dt.files;
            updatePreview();
        });

        function updatePreview() {
            prevCont.innerHTML = '';
            for(let i = 0; i < dt.files.length; i++) {
                const file = dt.files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}"><div class="btn-rm-img" onclick="removeFile(${i})"><i class="bi bi-x"></i></div>`;
                    prevCont.appendChild(div);
                }
                reader.readAsDataURL(file);
            }
        }

        window.removeFile = function(index) {
            dt.items.remove(index);
            fileIn.files = dt.files;
            updatePreview();
        }

        // 6. Submit Logic
        document.getElementById('productForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');

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

            // Loader
            btn.disabled = true;
            document.getElementById('btnText').classList.add('d-none');
            document.getElementById('btnSpinner').classList.remove('d-none');
        });

    });
</script>

<?php
// 4. Include Footer
require_once __DIR__ . '/includes/footer.php';
?>