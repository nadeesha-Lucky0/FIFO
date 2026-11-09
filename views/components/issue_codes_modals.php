<?php
// Simplified Modals for Add & Edit Issue Destination Codes
?>

<!-- 1. Add Issue Code Modal (Simple: Category, Code, Name) -->
<div class="modal-backdrop" id="createIssueCodeModal">
  <div class="modal-dialog" style="max-width: 500px;">
    <div class="modal-header">
      <h3 class="modal-title">Add New Code &amp; Name</h3>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="createIssueCodeForm">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
      <input type="hidden" name="display_label" id="newIssueCodeDisplayLabel" value="">

      <div class="modal-body" style="padding: 20px;">
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Category
            *</label>
          <select name="category" id="newIssueCodeCategory" class="form-select" required
            onchange="updateNewIssueCodePreview();">
            <option value="Section" selected>Section</option>
            <option value="Project">Project</option>
            <option value="Cost Center">Cost Center</option>
            <option value="Warehouse">Warehouse</option>
            <option value="My Project">My Project</option>
            <option value="Genaration as Expenditure">Genaration as Expenditure</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Code
            *</label>
          <input type="text" name="code" id="newIssueCodeCode" class="form-control" placeholder="e.g. A1004" required
            oninput="handleNewIssueCodeChange();"
            style="font-family: monospace; font-size: 0.95rem; font-weight: 700; text-transform: uppercase;">
          <div id="newIssueCodeDuplicateMsg"
            style="display: none; font-size: 0.75rem; color: #dc2626; font-weight: 700; margin-top: 4px; line-height: 1.2;">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Name /
            Description *</label>
          <input type="text" name="title" id="newIssueCodeTitle" class="form-control"
            placeholder="e.g. PROVISION FOR DEPRECIATION OF BUILDINGS" required oninput="updateNewIssueCodePreview();"
            style="text-transform: uppercase; font-weight: 600;">
        </div>

        <!-- Live Code Format Preview -->
        <div
          style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; padding: 10px 14px; margin-top: 10px;">
          <div
            style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
            Preview in Dropdown &amp; List:</div>
          <div id="newIssueCodePreviewText"
            style="font-size: 0.9rem; font-weight: 700; color: #0f172a; font-family: inherit;">
            A1004 -PROVISION FOR DEPRECIATION OF BUILDINGS
          </div>
        </div>
      </div>

      <div class="modal-footer"
        style="display: flex; justify-content: flex-end; gap: 10px; padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary"
          style="font-weight: 700; background: #650b2b; color: #fff; border: 1px solid #4a071e;">
          + Save Code
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Edit Issue Code Modal (Simple: Category, Code, Name) -->
<div class="modal-backdrop" id="editIssueCodeModal">
  <div class="modal-dialog" style="max-width: 500px;">
    <div class="modal-header">
      <h3 class="modal-title">Edit Code &amp; Name</h3>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="editIssueCodeForm">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
      <input type="hidden" name="id" id="editIssueCodeId" value="">
      <input type="hidden" name="display_label" id="editIssueCodeDisplayLabel" value="">
      <input type="hidden" name="sort_order" id="editIssueCodeSortOrder" value="0">

      <div class="modal-body" style="padding: 20px;">
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Category
            *</label>
          <select name="category" id="editIssueCodeCategory" class="form-select" required
            onchange="updateEditIssueCodePreview();">
            <option value="Section">Section</option>
            <option value="Project">Project</option>
            <option value="Cost Center">Cost Center</option>
            <option value="Warehouse">Warehouse</option>
            <option value="My Project">My Project</option>
            <option value="Genaration as Expenditure">Genaration as Expenditure</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Code
            *</label>
          <input type="text" name="code" id="editIssueCodeCode" class="form-control" required
            oninput="handleEditIssueCodeChange();"
            style="font-family: monospace; font-size: 0.95rem; font-weight: 700; text-transform: uppercase;">
          <div id="editIssueCodeDuplicateMsg"
            style="display: none; font-size: 0.75rem; color: #dc2626; font-weight: 700; margin-top: 4px; line-height: 1.2;">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Name /
            Description *</label>
          <input type="text" name="title" id="editIssueCodeTitle" class="form-control" required
            oninput="updateEditIssueCodePreview();" style="text-transform: uppercase; font-weight: 600;">
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label"
            style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">Status</label>
          <select name="status" id="editIssueCodeStatus" class="form-select">
            <option value="active">Active (Show in Dropdown)</option>
            <option value="inactive">Inactive (Hide from Dropdown)</option>
          </select>
        </div>

        <!-- Live Code Format Preview -->
        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; padding: 10px 14px;">
          <div
            style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
            Updated Display:</div>
          <div id="editIssueCodePreviewText"
            style="font-size: 0.9rem; font-weight: 700; color: #0f172a; font-family: inherit;">
            —
          </div>
        </div>
      </div>

      <div class="modal-footer"
        style="display: flex; justify-content: flex-end; gap: 10px; padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary"
          style="font-weight: 700; background: #650b2b; color: #fff; border: 1px solid #4a071e;">
          Update Code
        </button>
      </div>
    </form>
  </div>
</div>