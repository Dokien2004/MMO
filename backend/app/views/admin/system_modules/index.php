
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* UI KHOA HỌC: Tối giản, tập trung vào nội dung */
    .module-list-container {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    /* Grid Layout cho Header & Rows */
    .list-header, .list-row {
        display: grid;
        grid-template-columns: 50px 60px 250px 1fr 100px 100px; /* Điều chỉnh lại kích thước cột */
        gap: 15px;
        align-items: center;
        padding: 12px 20px;
    }

    .list-header {
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        font-weight: 700; color: #6c757d;
        text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;
    }

    .list-row {
        border-bottom: 1px solid #f1f3f5;
        transition: background 0.15s ease;
        background-color: #fff;
    }
    .list-row:last-child { border-bottom: none; }
    .list-row:hover { background-color: #f8faff; }
    
    /* Hiệu ứng kéo thả */
    .sortable-ghost { opacity: 0.4; background-color: #e2e6ea; border: 1px dashed #adb5bd; }
    .sortable-drag { background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.15); cursor: grabbing; z-index: 9999; }

    /* Components */
    .drag-handle { 
        cursor: grab; color: #adb5bd; display: flex; align-items: center; justify-content: center; 
        width: 30px; height: 30px; border-radius: 4px; transition: 0.2s;
    }
    .drag-handle:hover { color: #495057; background: #e9ecef; }
    
    .mod-icon { 
        width: 42px; height: 42px; background: #eef2f7; color: #5d6d7e; 
        border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; 
    }
    
    .mod-code { 
        font-family: 'Consolas', 'Monaco', monospace; 
        font-weight: 700; color: #d63384; background: #fff0f6; 
        padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; border: 1px solid #ffecf1;
    }
    
    .mod-name { font-weight: 600; color: #212529; font-size: 0.95rem; }
    .mod-desc { font-size: 0.85rem; color: #868e96; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

    /* Toggle Switch Custom */
    .form-switch .form-check-input { width: 2.5em; height: 1.25em; cursor: pointer; }
    .form-switch .form-check-input:checked { background-color: #20c997; border-color: #20c997; }

    /* Responsive */
    @media (max-width: 992px) {
        .list-header { display: none; } /* Ẩn header trên mobile */
        .list-row { 
            grid-template-columns: 1fr; 
            grid-template-rows: auto auto;
            gap: 10px; position: relative;
        }
        .drag-handle { position: absolute; top: 10px; right: 10px; }
        .col-desc { display: none; }
    }
</style>

<div class="container-fluid px-4 mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-dark fw-bold mb-1"><i class="fas fa-cubes me-2 text-primary"></i>Quản lý Modules</h4>
            <small class="text-muted">Định nghĩa menu hệ thống. Kéo thả biểu tượng <i class="fas fa-grip-vertical fa-xs"></i> để sắp xếp.</small>
        </div>
        <button class="btn btn-primary fw-bold shadow-sm" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i>Thêm Module
        </button>
    </div>

    <div id="alertBox">
        <?php if(function_exists('flash')) flash('sysmod_msg'); ?>
    </div>

    <div class="module-list-container">
        
        <div class="list-header d-none d-lg-grid">
            <div class="text-center">Sort</div>
            <div class="text-center">Icon</div>
            <div>Thông tin Module</div>
            <div>Mô tả chức năng</div>
            <div class="text-center">Active</div>
            <div class="text-end pe-2">Actions</div>
        </div>

        <div id="sortableList">
            <?php foreach($data['modules'] as $mod): ?>
                <div class="list-row" data-id="<?php echo $mod->id; ?>">
                    
                    <div class="d-flex justify-content-center">
                        <div class="drag-handle" title="Kéo để sắp xếp"><i class="fas fa-grip-vertical"></i></div>
                    </div>

                    <div class="d-flex justify-content-center">
                        <div class="mod-icon shadow-sm"><i class="<?php echo $mod->icon; ?>"></i></div>
                    </div>

                    <div class="d-flex flex-column justify-content-center">
                        <div class="mb-1"><span class="mod-code"><?php echo $mod->code; ?></span></div>
                        <div class="mod-name"><?php echo $mod->name; ?></div>
                    </div>

                    <div class="col-desc text-muted">
                        <?php echo $mod->description; ?>
                    </div>

                    <div class="d-flex justify-content-center align-items-center">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" onchange="toggleStatus(<?php echo $mod->id; ?>, this)" <?php echo ($mod->is_active) ? 'checked' : ''; ?>>
                        </div>
                    </div>

                    <div class="text-end pe-2">
                        <button class="btn btn-sm btn-light border text-primary me-1" 
                                data-json='<?php echo htmlspecialchars(json_encode($mod, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                onclick="editModule(this)" title="Sửa">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-light border text-danger" onclick="confirmDelete(<?php echo $mod->id; ?>)" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php $pagination = $data['pagination'] ?? null; $entityLabel = 'module'; require APP_VIEWS_PATH . '/layouts/_pagination.php'; ?>
</div>

<div class="modal fade" id="moduleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="" method="POST" id="moduleForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="modal-header bg-white border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary" id="modalTitle">Thêm Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body pt-4">
                    <div class="row g-3">
                        <div class="col-3 text-center">
                            <label class="form-label small fw-bold text-muted">Preview</label>
                            <div class="bg-light rounded p-3 d-flex align-items-center justify-content-center border" style="height: 80px;">
                                <i id="iconPreview" class="fas fa-cube fa-2x text-secondary"></i>
                            </div>
                        </div>

                        <div class="col-9">
                            <label class="form-label small fw-bold">Class Icon (FontAwesome)</label>
                            <input type="text" name="icon" id="inpIcon" class="form-control font-monospace" placeholder="fas fa-cube" oninput="updatePreview(this.value)">
                            <div class="form-text small">Ví dụ: <code class="text-primary">fas fa-cogs</code></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Mã Module (Unique) <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="inpCode" class="form-control text-uppercase fw-bold" placeholder="VD: INVENTORY" required>
                            <div class="form-text small" id="codeHint">Dùng để định danh quyền hạn. Không sửa được sau khi tạo.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Tên hiển thị <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="inpName" class="form-control" placeholder="VD: Quản lý Kho" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Mô tả ngắn</label>
                            <textarea name="description" id="inpDesc" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch bg-light p-3 rounded">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="is_active" id="inpActive" checked>
                                <label class="form-check-label fw-bold" for="inpActive">Kích hoạt hoạt động</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0 pb-4 pe-4">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Lưu dữ liệu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
</form>

<script>
    // Config CSRF cho Ajax
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

    // 1. INIT SORTABLE
    const el = document.getElementById('sortableList');
    const sortable = Sortable.create(el, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function (evt) {
            let itemEl = el.children;
            let ids = [];
            for (let i = 0; i < itemEl.length; i++) {
                ids.push(itemEl[i].getAttribute('data-id'));
            }
            saveOrder(ids);
        },
    });

    // 2. AJAX REORDER
    function saveOrder(ids) {
        fetch('<?= url('/admin') ?>/systemmodules/ajax_reorder', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN // Gửi token nếu cần
            },
            body: JSON.stringify({ ids: ids })
        });
    }

    // 3. AJAX TOGGLE STATUS
    function toggleStatus(id, checkbox) {
        const status = checkbox.checked ? 1 : 0;
        fetch('<?= url('/admin') ?>/systemmodules/ajax_toggle_status', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ id: id, status: status })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status !== 'success') {
                checkbox.checked = !checkbox.checked; // Revert nếu lỗi
                Swal.fire('Lỗi', 'Không thể cập nhật trạng thái', 'error');
            }
        });
    }

    // 4. UI LOGIC
    function updatePreview(val) {
        document.getElementById('iconPreview').className = val + ' fa-2x text-primary';
    }

    function openAddModal() {
        document.getElementById('moduleForm').action = '<?= url('/admin') ?>/systemmodules/add';
        document.getElementById('modalTitle').textContent = 'Thêm Module Mới';
        
        document.getElementById('inpCode').value = '';
        document.getElementById('inpCode').readOnly = false;
        document.getElementById('codeHint').style.display = 'block';
        
        document.getElementById('inpName').value = '';
        document.getElementById('inpIcon').value = 'fas fa-cube';
        document.getElementById('inpDesc').value = '';
        document.getElementById('inpActive').checked = true;
        updatePreview('fas fa-cube');
        
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    // Sửa Module: Lấy data từ attribute data-json
    function editModule(btn) {
        const data = JSON.parse(btn.getAttribute('data-json'));
        
        document.getElementById('moduleForm').action = '<?= url('/admin') ?>/systemmodules/edit/' + data.id;
        document.getElementById('modalTitle').textContent = 'Cập nhật Module';
        
        document.getElementById('inpCode').value = data.code;
        document.getElementById('inpCode').readOnly = false;
        document.getElementById('codeHint').style.display = 'block';
        
        document.getElementById('inpName').value = data.name;
        document.getElementById('inpIcon').value = data.icon;
        document.getElementById('inpDesc').value = data.description;
        document.getElementById('inpActive').checked = (data.is_active == 1);
        updatePreview(data.icon);
        
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    // Xóa Module: Dùng SweetAlert2
    function confirmDelete(id) {
        Swal.fire({
            title: 'Xác nhận xóa?',
            text: "Xóa module có thể ảnh hưởng đến phân quyền và menu. Bạn chắc chắn?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('deleteForm');
                form.action = '<?= url('/admin') ?>/systemmodules/delete/' + id;
                form.submit();
            }
        });
    }
</script>

