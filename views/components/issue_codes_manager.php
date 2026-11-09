<?php
// Separated Issue Destination Codes Manager (Simplified Clean UI)
$compIssueModel = new \App\Models\IssueDestinationCode();
$compCategoryCounts = $compIssueModel->getCategoryCounts();
$groupedIssueCodes = $compIssueModel->getAllGrouped();

// Define available categories with metadata
$categoryConfig = [
  'Section' => [
    'title' => 'Section',
    'code_placeholder' => 'e.g. A1004, A1113',
    'name_placeholder' => 'e.g. PROVISION FOR DEPRECIATION OF BUILDINGS',
    'count' => $compCategoryCounts['Section'] ?? 0
  ],
  'Project' => [
    'title' => 'Project',
    'code_placeholder' => 'e.g. HQ, PMU',
    'name_placeholder' => 'e.g. GENERATION HQ, PROJECT MANAGEMENT UNIT',
    'count' => $compCategoryCounts['Project'] ?? 0
  ],
  'Cost Center' => [
    'title' => 'Cost Center',
    'code_placeholder' => 'e.g. 830, 831, 832',
    'name_placeholder' => 'e.g. DGM(GEN) OFFICE, VICTORIA COMPLEX',
    'count' => $compCategoryCounts['Cost Center'] ?? 0
  ],
  'Warehouse' => [
    'title' => 'Warehouse',
    'code_placeholder' => 'e.g. WH-VIC, WH-KOT',
    'name_placeholder' => 'e.g. MAIN STORES - VICTORIA',
    'count' => $compCategoryCounts['Warehouse'] ?? 0
  ],
  'My Project' => [
    'title' => 'My Project',
    'code_placeholder' => 'e.g. VIC-P1, KOT-HYD',
    'name_placeholder' => 'e.g. VICTORIA POWER STATION',
    'count' => $compCategoryCounts['My Project'] ?? 0
  ],
  'Genaration as Expenditure' => [
    'title' => 'Genaration as Expenditure',
    'code_placeholder' => 'e.g. EXP-01, EXP-02',
    'name_placeholder' => 'e.g. EXPENDITURE - ROUTINE MAINTENANCE',
    'count' => $compCategoryCounts['Genaration as Expenditure'] ?? 0
  ]
];

// All records grouped for rendering
$allCodes = $compIssueModel->getAll();
?>

<div class="card"
  style="padding: 24px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 24px;">

  <!-- Main Title -->
  <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 20px;">
    <h2
      style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px;">
      <span
        style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #650b2b; color: #ffffff; border-radius: 6px; font-size: 0.85rem; font-weight: 700;">
        #
      </span>
      Issue Destination Codes
    </h2>
  </div>

  <!-- Category Selector -->
  <div style="margin-bottom: 20px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;"
      id="issueCategoryTabsContainer">
      <?php foreach ($categoryConfig as $catKey => $catInfo): ?>
        <button type="button" onclick="selectIssueCategory('<?= htmlspecialchars($catKey, ENT_QUOTES) ?>', this);"
          class="issue-section-tab <?= ($catKey === 'Section') ? 'active' : '' ?>"
          data-category-key="<?= htmlspecialchars($catKey, ENT_QUOTES) ?>"
          style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 6px; border: 1.5px solid <?= ($catKey === 'Section') ? '#650b2b' : '#cbd5e1' ?>; background: <?= ($catKey === 'Section') ? '#fdf2f4' : '#f8fafc' ?>; cursor: pointer; text-align: left; transition: all 0.15s ease;">
          <strong
            style="font-size: 0.85rem; font-weight: 700; color: <?= ($catKey === 'Section') ? '#650b2b' : '#0f172a' ?>;"><?= htmlspecialchars($catKey) ?></strong>
          <span class="badge"
            style="background: <?= ($catKey === 'Section') ? '#650b2b' : '#64748b' ?>; color: #fff; font-size: 0.72rem; padding: 2px 7px; border-radius: 10px;">
            <?= $catInfo['count'] ?>
          </span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Add Code Box -->
  <div id="dedicatedAddBox"
    style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 16px 18px; margin-bottom: 22px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">

    <div style="margin-bottom: 12px;">
      <h3 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
        Add to <span id="currentSelectedSectionTitle">Section</span>
      </h3>
    </div>

    <!-- Quick Add Form for Active Category -->
    <form id="quickAddIssueCodeForm" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="category" id="quickAddCategory" value="Section">
      <input type="hidden" name="display_label" id="quickAddDisplayLabel" value="">

      <!-- Code Input -->
      <div style="flex: 1 1 180px;">
        <label
          style="display: block; font-size: 0.74rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">
          Code <span style="color: #dc2626;">*</span>
        </label>
        <input type="text" name="code" id="quickAddCode" placeholder="e.g. A1004" required
          oninput="handleQuickAddCodeChange();"
          style="width: 100%; height: 38px; font-family: monospace; font-size: 0.95rem; font-weight: 700; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 10px; text-transform: uppercase; background: #ffffff;">
        <div id="quickAddCodeDuplicateMsg"
          style="display: none; font-size: 0.75rem; color: #dc2626; font-weight: 700; margin-top: 4px; line-height: 1.2;">
        </div>
      </div>

      <!-- Name / Description Input -->
      <div style="flex: 2 1 320px;">
        <label
          style="display: block; font-size: 0.74rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">
          Name / Description <span style="color: #dc2626;">*</span>
        </label>
        <input type="text" name="title" id="quickAddTitle" placeholder="e.g. PROVISION FOR DEPRECIATION OF BUILDINGS"
          required oninput="updateQuickAddDisplayPreview();"
          style="width: 100%; height: 38px; font-size: 0.88rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 10px; text-transform: uppercase; background: #ffffff;">
      </div>

      <!-- Submit Button -->
      <div>
        <button type="submit" id="btnQuickAddSubmit"
          style="height: 38px; padding: 0 22px; font-size: 0.88rem; font-weight: 700; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; border-radius: 4px; cursor: pointer; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
          + Add
        </button>
      </div>
    </form>

  </div>

  <!-- Code List -->
  <div>

    <div
      style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <strong id="currentListCategoryBadge" style="font-size: 0.95rem; font-weight: 700; color: #0f172a;">
          Section
        </strong>
        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">
          (<span
            id="selectedCategoryCount"><?= count(array_filter($allCodes, fn($c) => $c['category'] === 'Section')) ?></span>)
        </span>
      </div>

      <!-- Quick Search Within This Category -->
      <div style="position: relative; width: 260px;">
        <input type="text" id="issueCodeSearchInput" placeholder="Search..." oninput="filterIssueCodesTable();"
          style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; font-size: 0.84rem; background: #ffffff;">
        <button type="button" id="clearIssueCodeSearchBtn"
          onclick="document.getElementById('issueCodeSearchInput').value=''; filterIssueCodesTable();"
          style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer;">&times;</button>
      </div>
    </div>

    <!-- Hidden category input synced with category tabs -->
    <input type="hidden" id="issueCodeCategoryFilter" value="Section">
    <input type="hidden" id="issueCodeStatusFilter" value="all">

    <!-- Container of Items -->
    <div
      style="border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">

      <!-- List Header -->
      <div
        style="display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
        <div style="flex: 1; padding: 0 4px;">Code &amp; Name</div>
        <div style="width: 140px; text-align: right;">Action</div>
      </div>

      <div id="issueCodesListItems">
        <?php foreach ($allCodes as $idx => $item): ?>
          <?php
          $cat = $item['category'];
          $displayFormatted = $item['display_label'] ?: (($item['code'] ? $item['code'] . ' -' : '') . $item['title']);
          $isSection = ($cat === 'Section');
          ?>
          <div id="issue-code-row-<?= $item['id'] ?>" class="issue-code-row"
            data-category="<?= htmlspecialchars($item['category']) ?>"
            data-status="<?= htmlspecialchars($item['status']) ?>"
            data-search="<?= htmlspecialchars(strtolower($item['category'] . ' ' . ($item['code'] ?? '') . ' ' . $item['title'] . ' ' . $displayFormatted)) ?>"
            style="display: <?= $isSection ? 'flex' : 'none' ?>; justify-content: space-between; align-items: center; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; transition: background 0.1s ease;">

            <div
              style="flex: 1; padding: 0 4px; font-size: 0.9rem; font-weight: 600; color: #0f172a; word-break: break-word;">
              <?= htmlspecialchars($displayFormatted) ?>
            </div>

            <!-- Action Buttons -->
            <div style="width: 140px; text-align: right; flex-shrink: 0; white-space: nowrap;">
              <button type="button" class="btn btn-secondary btn-sm"
                onclick="openEditIssueCodeModal(<?= (int) $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['category'])) ?>', '<?= addslashes(htmlspecialchars($item['code'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($item['title'])) ?>', '<?= addslashes(htmlspecialchars($displayFormatted)) ?>', '<?= htmlspecialchars($item['status']) ?>', <?= (int) $item['sort_order'] ?>)"
                style="font-size: 0.78rem; padding: 4px 10px; border-radius: 3px; cursor: pointer;">
                Edit
              </button>
              <button type="button" class="btn btn-danger btn-sm"
                style="margin-left: 4px; font-size: 0.78rem; padding: 4px 10px; border-radius: 3px; cursor: pointer;"
                onclick="deleteIssueCode(<?= (int) $item['id'] ?>, '<?= addslashes(htmlspecialchars($displayFormatted)) ?>')">
                Delete
              </button>
            </div>

          </div>
        <?php endforeach; ?>

        <!-- Empty state placeholder -->
        <div id="emptyIssueCodesRow"
          style="display: none; text-align: center; padding: 32px 20px; color: #94a3b8; font-size: 0.88rem;">
          No codes found.
        </div>
      </div>

    </div>

  </div>

</div>

<!-- Pass Category Metadata and Existing Codes to JavaScript -->
<script>
  window.ISSUE_CATEGORIES_CONFIG = <?= json_encode($categoryConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  window.EXISTING_ISSUE_CODES = <?= json_encode(array_values(array_map(function ($c) {
    return [
      'id' => (int) $c['id'],
      'category' => $c['category'],
      'code' => trim((string) $c['code']),
      'title' => $c['title'],
      'display_label' => $c['display_label']
    ];
  }, array_filter($allCodes, fn($c) => !empty(trim((string) $c['code']))))), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>