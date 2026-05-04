
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    :root {
        --oracle-bg: #f5f7fa;
        --flow-connector: #cbd5e1;
        --node-start: #10b981; /* Green */
        --node-approval: #3b82f6; /* Blue */
        --node-end: #64748b; /* Gray */
        --active-tab-bg: #e0f2fe;
        --active-tab-border: #3b82f6;
    }

    body { background-color: var(--oracle-bg); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }

    /* --- 1. STICKY HEADER & TOOLBAR --- */
    .master-sticky-header {
        position: sticky; top: 0; z-index: 100; background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-bottom: 1px solid #e2e8f0;
    }
    .header-toolbar { padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; }

    /* --- 2. MODULE GRID TABS --- */
    .ns-tabs-wrapper {
        position: relative; background: #fff; padding: 15px 20px; transition: all 0.3s ease;
    }
    .ns-tabs-grid {
        display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px;
        overflow: hidden; max-height: 95px; transition: max-height 0.4s ease-in-out;
    }
    .ns-tabs-grid.expanded { max-height: 500px; }

    .ns-tab-item {
        display: flex; align-items: center; justify-content: center;
        padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
        background: #fff; color: #64748b; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;
        cursor: pointer; transition: all 0.2s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ns-tab-item:hover { background: #f8fafc; border-color: #cbd5e1; color: #334155; transform: translateY(-1px); }
    .ns-tab-item.active {
        background: var(--active-tab-bg); border-color: var(--active-tab-border); color: #0369a1;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15);
    }

    .tabs-expander { display: flex; justify-content: center; margin-top: 5px; }
    .btn-expander {
        font-size: 0.7rem; color: #94a3b8; cursor: pointer; padding: 2px 10px;
        border-radius: 10px; background: #f1f5f9; transition: 0.2s; border: none;
    }
    .btn-expander:hover { background: #e2e8f0; color: #475569; }

    /* --- 3. WORKFLOW CANVAS --- */
    .workflow-canvas { padding: 25px; min-height: calc(100vh - 180px); }
    
    .module-pipeline-card {
        background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;
        margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); overflow: hidden;
    }
    .pipeline-header {
        padding: 12px 20px; background: #fff; border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center;
    }

    .pipeline-scroll-container {
        padding: 30px 20px 40px 20px; overflow-x: auto; background: #fcfdfe;
        background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 20px 20px;
    }
    .pipeline-flex { display: flex; align-items: stretch; gap: 50px; }

    /* Node Design */
    .flow-node-wrapper { position: relative; display: flex; align-items: center; z-index: 2; }
    .flow-node-wrapper:not(:last-child)::after {
        content: ''; position: absolute; right: -50px; top: 50%;
        width: 50px; height: 2px; background: var(--flow-connector); z-index: -1;
    }
    .flow-node-wrapper:not(:last-child)::before {
        content: '\f0da'; font-family: "Font Awesome 6 Free"; font-weight: 900;
        position: absolute; right: -32px; top: 50%; transform: translateY(-50%);
        color: var(--flow-connector); font-size: 18px; background: #fcfdfe; padding: 0 5px;
    }

    .node-card {
        width: 240px; background: #fff; border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;
        position: relative; transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden; display: flex; flex-direction: column;
    }
    .node-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -3px rgba(0,0,0,0.12); border-color: #cbd5e1; }

    .node-card-header { padding: 8px 12px; font-weight: 700; color: #fff; display: flex; align-items: center; font-size: 0.85rem; justify-content: space-between; }
    .node-card-body { padding: 12px; flex-grow: 1; }

    .type-START .node-card-header { background: var(--node-start); }
    .type-APPROVAL .node-card-header { background: var(--node-approval); }
    .type-END .node-card-header { background: var(--node-end); }

    .btn-node-action { color: rgba(255,255,255,0.8); padding: 0 5px; cursor: pointer; }
    .btn-node-action:hover { color: #fff; }

    .badge-condition { font-size: 0.75rem; background: #fff7ed; color: #c2410c; padding: 4px 8px; border-radius: 4px; display: block; margin-top: 8px; border: 1px solid #ffedd5; }
    .badge-strategy { font-size: 0.7rem; text-transform: uppercase; color: #64748b; margin-top: 5px; display: block; font-weight: 700; letter-spacing: 0.5px; }
    .badge-logic { position:absolute; top:32px; right:10px; font-size:0.65rem; background:#eff6ff; color:#1d4ed8; padding:2px 6px; border-radius:4px; border:1px solid #dbeafe; font-weight:bold;}

    .add-step-btn {
        width: 50px; height: 50px; border-radius: 50%; background: #fff;
        border: 2px dashed #cbd5e1; color: #94a3b8; display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .add-step-btn:hover { border-color: var(--node-approval); color: var(--node-approval); background: #f8fafc; transform: scale(1.05); }

    .modal-header-enterprise { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
</style>

<div class="container-fluid p-0">
    <div class="master-sticky-header">
        <div class="header-toolbar">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 p-2 rounded me-3 text-primary">
                    <i class="fas fa-project-diagram fa-lg"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark">Cấu hình Quy trình Duyệt</h5>
                    <div class="small text-muted d-flex align-items-center mt-1">
                        <span class="badge bg-light text-dark border me-2"><i class="fas fa-building me-1"></i> Site ID: <?php echo $data['current_site_id']; ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('/admin') ?>/sites" class="btn btn-outline-secondary btn-sm fw-bold">
                    <i class="fas fa-exchange-alt me-1"></i> Đổi Site
                </a>
                <?php if(true): /* Check Permission here */ ?>
                <button class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="openModal('add')">
                    <i class="fas fa-plus-circle me-2"></i> Tạo Mới
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="ns-tabs-wrapper">
            <div class="ns-tabs-grid" id="tabsGrid">
                <div class="ns-tab-item <?php echo ($data['filter_module'] == '') ? 'active' : ''; ?>" onclick="filterTab('all', this)">
                    <i class="fas fa-th-large"></i> TẤT CẢ
                </div>
                <?php foreach($data['modules_list'] as $mod): ?>
                    <div class="ns-tab-item <?php echo ($data['filter_module'] == $mod->code) ? 'active' : ''; ?>" onclick="filterTab('<?php echo $mod->code; ?>', this)" title="<?php echo $mod->name; ?>">
                        <i class="<?php echo $mod->icon ?? 'fas fa-cube'; ?>"></i> <?php echo $mod->name; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="tabs-expander">
                <button class="btn-expander" onclick="toggleTabs()" id="btnExpandTabs">
                    <i class="fas fa-chevron-down me-1"></i> Xem thêm
                </button>
            </div>
        </div>
    </div>
    
    <div class="workflow-canvas bg-light">
        <?php if(function_exists('flash')) flash('msg'); ?>

        <?php if(empty($data['grouped_configs'])): ?>
            <div class="text-center p-5 bg-white rounded shadow-sm border border-dashed mt-4">
                <h5 class="fw-bold text-muted">Chưa có quy trình nào.</h5>
                <p class="text-secondary small mb-4">Chọn nút "Tạo Mới" để bắt đầu thiết lập.</p>
                <button class="btn btn-outline-primary btn-sm px-4 fw-bold" onclick="openModal('add')">Bắt đầu ngay</button>
            </div>
        <?php else: ?>
            
            <?php foreach($data['grouped_configs'] as $module => $steps): 
                $displayStyle = ($data['filter_module'] != '' && $data['filter_module'] != $module) ? 'display:none;' : '';
                $workflowId = $steps[0]->def_id ?? 0;
                $workflowName = $steps[0]->workflow_name ?? $module;
            ?>
                <div class="module-pipeline-card fade-in" data-module="<?php echo $module; ?>" style="<?php echo $displayStyle; ?>">
                    <div class="pipeline-header">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-primary"><i class="fas fa-sitemap fa-lg"></i></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?php echo $workflowName; ?> <span class="text-muted small fw-normal">(<?php echo $module; ?>)</span></h6>
                            </div>
                        </div>
                        <?php if(!empty($steps)): ?>
                            <button class="btn btn-outline-danger btn-sm fw-bold border-0 bg-danger bg-opacity-10" onclick="confirmDeleteWorkflow(<?php echo $workflowId; ?>, '<?php echo $module; ?>')">
                                <i class="far fa-trash-alt me-1"></i> Xóa Quy trình
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="pipeline-scroll-container">
                        <div class="pipeline-flex">
                            
                            <?php foreach($steps as $index => $step): 
                                $isStart = ($step->node_type == 'START');
                                $nodeTypeClass = $isStart ? 'type-START' : 'type-APPROVAL';
                                $icon = $isStart ? 'fas fa-flag' : 'fas fa-user-check';
                                
                                // Parse Logic Hiển thị Condition
                                $conditionRule = json_decode($step->condition_rule ?? '{}', true);
                                $ruleText = "";
                                if(isset($conditionRule['field'])) {
                                    $fName = $conditionRule['field'];
                                    if($fName == 'total_amount') $fName = 'Tổng tiền';
                                    if($fName == 'margin_percentage') $fName = '% Lãi';
                                    if($fName == 'department_id') {
                                        $fName = 'Phòng ban';
                                        $deptIds = $conditionRule['value'] ?? [];
                                        $deptNames = [];
                                        foreach ((array)$deptIds as $did) {
                                            $deptNames[] = $data['dept_map'][$did] ?? "PB #$did";
                                        }
                                        $ruleText = "$fName: " . implode(', ', $deptNames);
                                    } else {
                                        $val = number_format($conditionRule['value']);
                                        $op  = $conditionRule['operator'];
                                        $ruleText = "$fName $op $val";
                                    }
                                }

                                // Parse Logic Hiển thị Strategy
                                $stratCode = $step->resolution_strategy ?? 'STATIC_ROLE';
                                $stratNames = [
                                    'STATIC_USER' => 'NGƯỜI CỤ THỂ',
                                    'STATIC_ROLE' => 'THEO VAI TRÒ',
                                    'JOB_LIMIT'   => 'THEO HẠN MỨC',
                                    'DEPT_HEAD'   => 'TRƯỞNG BỘ PHẬN',
                                    'APPROVAL_GROUP' => 'NHÓM DUYỆT'
                                ];
                                $stratDisplay = $stratNames[$stratCode] ?? $stratCode;
                                
                                $roleDisplay = "N/A";
                                if ($stratCode == 'STATIC_USER') $roleDisplay = $step->user_full_name ?? ('User #' . $step->resolution_value);
                                elseif ($stratCode == 'STATIC_ROLE') $roleDisplay = $step->role_name;
                                elseif ($stratCode == 'DEPT_HEAD') $roleDisplay = "Quản lý trực tiếp";
                                elseif ($stratCode == 'JOB_LIMIT') $roleDisplay = "Tự động (Job Level)";
                                elseif ($stratCode == 'APPROVAL_GROUP') $roleDisplay = "Nhóm " . $step->resolution_value;

                                // Logic ALL/ANY
                                $logicLabel = ($step->approval_logic == 'ALL') ? 'ALL' : '';
                            ?>
                                <div class="flow-node-wrapper">
                                    <div class="node-card <?php echo $nodeTypeClass; ?>">
                                        <div class="node-card-header">
                                            <div><i class="<?php echo $icon; ?> me-2"></i> <?php echo ($isStart ? 'START' : 'BƯỚC '.($index)); ?></div>
                                            
                                            <?php if(!$isStart): ?>
                                            <div class="dropdown">
                                                <i class="fas fa-ellipsis-v btn-node-action" data-bs-toggle="dropdown"></i>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                    <li><a class="dropdown-item small" href="#" onclick='editStep(<?php echo htmlspecialchars(json_encode($step, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>)'><i class="fas fa-pen text-primary me-2"></i> Sửa</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item small text-danger" href="#" onclick="confirmDelete(<?php echo $step->id; ?>)"><i class="fas fa-trash me-2"></i> Xóa</a></li>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="node-card-body">
                                            <div class="fw-bold text-dark mb-1 text-truncate" title="<?php echo $step->step_name; ?>">
                                                <?php echo $step->step_name; ?>
                                            </div>
                                            
                                            <?php if(!$isStart): ?>
                                                <?php if($logicLabel): ?>
                                                    <div class="badge-logic" title="Cần tất cả mọi người duyệt">ALL</div>
                                                <?php endif; ?>

                                                <div class="small text-muted mb-2">
                                                    <i class="fas fa-id-badge me-1"></i> <?php echo $roleDisplay; ?>
                                                </div>
                                                <span class="badge-strategy"><?php echo $stratDisplay; ?></span>
                                                
                                                <?php if($ruleText): ?>
                                                <div class="badge-condition" title="Điều kiện kích hoạt">
                                                    <i class="fas fa-filter me-1"></i> <?php echo $ruleText; ?>
                                                </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="small text-muted mt-2">Khởi tạo quy trình</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="flow-node-wrapper">
                                <div class="d-flex flex-column align-items-center">
                                    <button type="button" class="add-step-btn" onclick="openModal('add', {module: '<?php echo $module; ?>'})" title="Thêm bước tiếp theo">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <div class="small fw-bold text-muted mt-2">Thêm bước</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalApproval" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="" method="POST" id="formApproval">
                <input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="module_redirect" id="modRedirect"> 
                
                <div class="modal-header modal-header-enterprise p-3">
                    <h5 class="modal-title fw-bold text-dark" id="modalTitle">Cấu hình Bước Duyệt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        
                        <div class="col-md-12" id="groupModule">
                            <label class="form-label small fw-bold">Module áp dụng <span class="text-danger">*</span></label>
                            <select name="module" id="modModule" class="form-select bg-light">
                                <option value="">-- Chọn Module --</option>
                                <?php foreach($data['modules_list'] as $mod): ?>
                                    <option value="<?php echo $mod->code; ?>"><?php echo $mod->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Tên bước duyệt <span class="text-danger">*</span></label>
                            <input type="text" name="step_name" id="modName" class="form-control" required placeholder="VD: Giám đốc duyệt">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Nguyên tắc duyệt</label>
                            <select name="approval_logic" id="modLogic" class="form-select border-primary bg-blue-50 text-primary fw-bold">
                                <option value="ANY">✅ Bất kỳ ai (First wins)</option>
                                <option value="ALL">👥 Tất cả đồng thuận (Consensus)</option>
                            </select>
                            <div class="form-text small" style="font-size: 11px;">"Tất cả" nghĩa là toàn bộ người trong danh sách phải duyệt.</div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="col-md-6">
                            <div class="card h-100 bg-light border-0">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold small text-primary mb-3 text-uppercase">1. Người thực hiện</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Chiến lược tìm người</label>
                                        <select name="strategy" id="modStrategy" class="form-select" onchange="toggleStrategy()">
                                            <option value="STATIC_USER">Người cụ thể (User)</option>
                                            <option value="STATIC_ROLE">Theo Vai trò (Role)</option>
                                            <option value="DEPT_HEAD">Quản lý trực tiếp (Manager)</option>
                                            <option value="JOB_LIMIT">Theo Hạn mức Tiền</option>
                                            <option value="APPROVAL_GROUP">Nhóm Duyệt Động</option>
                                        </select>
                                    </div>

                                    <div id="field-user" style="display:none;">
                                        <label class="form-label small fw-bold">Chọn Người duyệt <span class="text-danger">*</span></label>
                                        <select name="user_id" id="modUser" class="form-select select2-modal-user">
                                            <option value="">-- Tìm người duyệt --</option>
                                            <?php foreach($data['users'] as $u): ?>
                                                <option value="<?php echo $u->id; ?>"><?php echo e($u->full_name); ?> (<?php echo e($u->username); ?><?php echo $u->department_name ? ' - ' . e($u->department_name) : ''; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div id="field-role">
                                        <label class="form-label small fw-bold">Chọn Vai trò <span class="text-danger">*</span></label>
                                        <select name="role_id" id="modRole" class="form-select select2-modal">
                                            <option value="">-- Tìm vai trò --</option>
                                            <?php foreach($data['roles'] as $r): ?>
                                                <option value="<?php echo $r->id; ?>"><?php echo $r->name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="field-hint" class="small text-muted fst-italic mt-2" style="display:none;">
                                        <i class="fas fa-info-circle me-1"></i> Hệ thống sẽ tự động xác định người duyệt dựa trên sơ đồ tổ chức hoặc quy tắc.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100 border-warning border-opacity-25 shadow-sm" style="background: #fffcf5;">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold small text-warning mb-3 text-uppercase">2. Điều kiện kích hoạt</h6>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Trường dữ liệu</label>
                                            <select name="rule_field" id="ruleField" class="form-select form-select-sm bg-white mb-2" onchange="toggleRuleField()">
                                                <option value="">(Luôn luôn duyệt)</option>
                                                <option value="total_amount">Tổng tiền đơn hàng</option>
                                                <option value="margin_percentage">% Lợi nhuận (Margin)</option>
                                                <option value="department_id">Phòng ban người tạo</option>
                                            </select>
                                        </div>
                                        <div id="rule-numeric-fields">
                                            <div class="row g-2">
                                                <div class="col-5">
                                                    <select name="rule_operator" id="ruleOperator" class="form-select form-select-sm bg-white">
                                                        <option value=">=">>= (Lớn hơn)</option>
                                                        <option value="<">< (Nhỏ hơn)</option>
                                                        <option value="=">= (Bằng)</option>
                                                    </select>
                                                </div>
                                                <div class="col-7">
                                                    <input type="text" name="rule_value" id="ruleValue" class="form-control form-control-sm text-end fw-bold" placeholder="Giá trị...">
                                                </div>
                                            </div>
                                            <div class="form-text small mt-2">Ví dụ: Tổng tiền >= 50,000,000</div>
                                        </div>
                                        <div id="rule-dept-fields" style="display:none;">
                                            <div class="col-12">
                                                <label class="form-label small fw-bold">Chọn phòng ban <span class="text-danger">*</span></label>
                                                <select name="rule_dept_ids[]" id="ruleDeptIds" class="form-select select2-dept-condition" multiple>
                                                    <?php foreach($data['departments'] as $dept): ?>
                                                        <option value="<?php echo $dept->id; ?>"><?php echo e($dept->name); ?> (<?php echo e($dept->code); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-text small mt-2">Chỉ áp dụng bước này khi người tạo thuộc phòng ban đã chọn.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Lưu Cấu Hình</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" action=""><input type="hidden" name="csrf_token" value="<?php echo $data['csrf_token']; ?>"></form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.10.0/dist/autoNumeric.min.js"></script>

<script>
    $(document).ready(function() {
        // Init Select2
        $('.select2-modal').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalApproval'), width: '100%', placeholder: '-- Tìm kiếm --' });
        $('.select2-modal-user').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalApproval'), width: '100%', placeholder: '-- Tìm người duyệt --' });
        // Select2 dept-condition: init lazily trong toggleRuleField() (tránh lỗi hidden element)
        
        // Init AutoNumeric
        if (AutoNumeric.getAutoNumericElement('#ruleValue') === null) {
             new AutoNumeric('#ruleValue', { digitGroupSeparator: ',', decimalPlaces: 0, minimumValue: '0' });
        }

        // Active Tab Logic
        const activeTab = '<?php echo $data['filter_module']; ?>';
        if(activeTab) {
            $('.module-pipeline-card').hide();
            $('.module-pipeline-card[data-module="'+activeTab+'"]').fadeIn();
        }
    });

    // --- TABS EXPANDER ---
    let isExpanded = false;
    function toggleTabs() {
        const grid = document.getElementById('tabsGrid');
        const btn = document.getElementById('btnExpandTabs');
        
        if(!isExpanded) {
            grid.classList.add('expanded');
            btn.innerHTML = '<i class="fas fa-chevron-up me-1"></i> Thu gọn';
        } else {
            grid.classList.remove('expanded');
            btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i> Xem thêm';
        }
        isExpanded = !isExpanded;
    }

    function toggleStrategy() {
        let strategy = $('#modStrategy').val();
        if(strategy === 'STATIC_ROLE') {
            $('#field-role').show();
            $('#field-user').hide();
            $('#field-hint').hide();
            $('#modRole').attr('required', true);
            $('#modUser').removeAttr('required');
        } else if(strategy === 'STATIC_USER') {
            $('#field-role').hide();
            $('#field-user').show();
            $('#field-hint').hide();
            $('#modRole').removeAttr('required');
            $('#modUser').attr('required', true);
        } else {
            $('#field-role').hide();
            $('#field-user').hide();
            $('#field-hint').show();
            $('#modRole').removeAttr('required');
            $('#modUser').removeAttr('required');
        }
    }

    function toggleRuleField() {
        let field = $('#ruleField').val();
        if (field === 'department_id') {
            $('#rule-numeric-fields').hide();
            $('#rule-dept-fields').show();
            // Re-init Select2 sau khi element visible (tránh lỗi hidden init)
            if ($('#ruleDeptIds').data('select2')) {
                $('#ruleDeptIds').select2('destroy');
            }
            $('#ruleDeptIds').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalApproval'), width: '100%', placeholder: '-- Chọn phòng ban --' });
        } else {
            $('#rule-numeric-fields').show();
            $('#rule-dept-fields').hide();
            $('#ruleDeptIds').val([]).trigger('change');
        }
    }

    function openModal(mode, data = {}) {
        $('#formApproval')[0].reset();
        $('#modRole').val('').trigger('change');
        $('#modUser').val('').trigger('change');
        $('#ruleDeptIds').val([]).trigger('change');
        $('#modStrategy').val('STATIC_ROLE').trigger('change');
        toggleStrategy();
        toggleRuleField();
        $('#modLogic').val('ANY');
        AutoNumeric.getAutoNumericElement('#ruleValue').set(0);
        
        if (mode === 'add') {
            $('#modalTitle').text('Thêm Bước Mới');
            $('#formApproval').attr('action', '<?= url('/admin') ?>/approval/add');
            $('#groupModule').show();
            $('#modModule').attr('required', true);
            if(data.module) {
                $('#modModule').val(data.module);
                let count = $('.module-pipeline-card[data-module="'+data.module+'"] .node-card').length; 
                $('#modName').val('Bước duyệt cấp ' + count);
            }
        }
        new bootstrap.Modal(document.getElementById('modalApproval')).show();
    }

    function editStep(step) {
        $('#modalTitle').text('Cập nhật Bước Duyệt');
        $('#formApproval').attr('action', '<?= url('/admin') ?>/approval/edit/' + step.id);
        
        $('#groupModule').hide(); 
        $('#modModule').removeAttr('required'); 
        $('#modRedirect').val(step.module);

        $('#modName').val(step.step_name);
        
        // [NEW] Logic Duyệt
        let logic = step.approval_logic || 'ANY';
        $('#modLogic').val(logic);

        // Strategy & Role/User
        let strat = step.resolution_strategy || 'STATIC_ROLE';
        $('#modStrategy').val(strat).trigger('change');
        toggleStrategy();
        
        if(strat === 'STATIC_ROLE') {
            $('#modRole').val(step.role_id).trigger('change'); 
        } else if(strat === 'STATIC_USER') {
            $('#modUser').val(step.resolution_value).trigger('change');
        }

        // Rule JSON parsing
        try {
            let rule = JSON.parse(step.condition_rule || '{}');
            if(rule.field) {
                $('#ruleField').val(rule.field);
                toggleRuleField();
                if (rule.field === 'department_id' && Array.isArray(rule.value)) {
                    $('#ruleDeptIds').val(rule.value.map(String)).trigger('change');
                } else {
                    $('#ruleOperator').val(rule.operator);
                    AutoNumeric.getAutoNumericElement('#ruleValue').set(rule.value);
                }
            } else {
                $('#ruleField').val('');
                toggleRuleField();
                AutoNumeric.getAutoNumericElement('#ruleValue').set(0);
            }
        } catch(e){}
        
        new bootstrap.Modal(document.getElementById('modalApproval')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Gỡ bỏ bước này?',
            text: "Quy trình sẽ tự động nối liền.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Đồng ý xóa'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteForm').attr('action', '<?= url('/admin') ?>/approval/delete/' + id).submit();
            }
        });
    }

    function confirmDeleteWorkflow(id, moduleName) {
        Swal.fire({
            title: 'Xóa Quy trình ' + moduleName + '?',
            text: "Hành động này không thể hoàn tác!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Xóa sạch'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteForm').attr('action', '<?= url('/admin') ?>/approval/delete_workflow/' + id).submit();
            }
        });
    }

    function filterTab(mod) {
        const url = new URL(window.location);
        if(mod !== 'all') url.searchParams.set('module', mod); else url.searchParams.delete('module');
        window.location.href = url;
    }
</script>

