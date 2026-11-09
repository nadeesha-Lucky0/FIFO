<?php
/**
 * Item Search Module Component
 * Reusable between User Portal (Staff) and Admin Portal (Code Admin)
 * 
 * @var string $searchPrefix Prefix for DOM IDs ('user' or 'admin')
 * @var array $allTiersGrouped Grouped tiers from MasterItem model
 * @var int $totalLibraryCount Total library items count
 */
$prefix = $searchPrefix ?? 'user';
$t1List = $allTiersGrouped['tier1'] ?? [];
?>

<style>
  .modern-page-btn,
  .master-page-btn {
    appearance: none !important;
    -webkit-appearance: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 32px !important;
    height: 32px !important;
    padding: 0 8px !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
    font-size: 0.8125rem !important;
    font-weight: 600 !important;
    color: #334155 !important;
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 5px !important;
    cursor: pointer !important;
    outline: none !important;
    transition: all 0.15s ease-in-out !important;
    user-select: none !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    text-decoration: none !important;
    line-height: 1 !important;
  }
  .modern-page-btn:hover:not(:disabled),
  .master-page-btn:hover:not(:disabled) {
    background-color: #f1f5f9 !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08) !important;
  }
  .modern-page-btn.active,
  .master-page-btn.active {
    background: #650b2b !important;
    background-color: #650b2b !important;
    border-color: #4a071e !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: 0 2px 6px rgba(101, 11, 43, 0.35) !important;
  }
  .modern-page-btn:disabled,
  .master-page-btn:disabled {
    opacity: 0.35 !important;
    cursor: not-allowed !important;
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
    color: #94a3b8 !important;
    box-shadow: none !important;
    transform: none !important;
  }
  .modern-page-ellipsis {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 24px !important;
    height: 32px !important;
    color: #94a3b8 !important;
    font-weight: 800 !important;
    font-size: 0.85rem !important;
    user-select: none !important;
  }
</style>

<div class="item-search-module-card" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 0; overflow: hidden; margin-bottom: 24px;">
  
  <!-- Header Bar -->
  <div style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">
        Inventory Master Item Search
      </h3>
      <p style="margin: 2px 0 0 0; font-size: 0.78rem; color: #64748b;">
        Live search across catalog items, registered master codes, specifications, and power station items.
      </p>
    </div>
  </div>

  <!-- Search & Filters Toolbar -->
  <div style="padding: 16px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0;">
    <!-- Live Search Input -->
    <div style="display: flex; gap: 10px; margin-bottom: 12px; align-items: center; flex-wrap: wrap;">
      <div style="flex: 2 1 320px; position: relative;">
        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; display: flex; align-items: center;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </span>
        <input type="text" id="<?= $prefix ?>ItemSearchQuery"
          placeholder="Search by item name, description, keywords, raw code (10101001), or code (01.01.01.001)..."
          oninput="window.itemSearchController['<?= $prefix ?>']?.onSearchInput();"
          style="width: 100%; height: 38px; padding: 6px 36px 6px 36px; font-size: 0.88rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; background: #ffffff; box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);">
        <button type="button" id="<?= $prefix ?>ItemSearchClearBtn"
          onclick="window.itemSearchController['<?= $prefix ?>']?.clearSearchInput();"
          style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer; padding: 0 4px; line-height: 1;"
          title="Clear search text">&times;</button>
      </div>

      <button type="button" onclick="window.itemSearchController['<?= $prefix ?>']?.resetFilters();"
        style="height: 38px; padding: 0 14px; font-size: 0.8rem; font-weight: 600; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; border-radius: 4px; cursor: pointer; white-space: nowrap;">
        Reset Filters
      </button>
    </div>

    <!-- Hierarchy & Status Cascading Filters Row -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <!-- Tier 1 Filter -->
      <div style="flex: 1 1 200px;">
        <label for="<?= $prefix ?>ItemSearchTier1" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 2px;">
          1st Code (Main Topic)
        </label>
        <select id="<?= $prefix ?>ItemSearchTier1" onchange="window.itemSearchController['<?= $prefix ?>']?.onTier1Change();"
          style="width: 100%; height: 34px; font-size: 0.8rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 8px; background: #ffffff;">
          <option value="">All 1st Categories</option>
          <?php foreach ($t1List as $t1): ?>
            <option value="<?= (int)$t1['code_val'] ?>" data-db-id="<?= (int)$t1['id'] ?>">
              <?= sprintf('%02d.', (int)$t1['code_val']) ?> <?= htmlspecialchars($t1['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tier 2 Filter -->
      <div style="flex: 1 1 200px;">
        <label for="<?= $prefix ?>ItemSearchTier2" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 2px;">
          2nd Code (Sub Topic)
        </label>
        <select id="<?= $prefix ?>ItemSearchTier2" onchange="window.itemSearchController['<?= $prefix ?>']?.onTier2Change();" disabled
          style="width: 100%; height: 34px; font-size: 0.8rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 8px; background: #f1f5f9; opacity: 0.7;">
          <option value="">All 2nd Sub Topics</option>
        </select>
      </div>

      <!-- Tier 3 Filter -->
      <div style="flex: 1 1 200px;">
        <label for="<?= $prefix ?>ItemSearchTier3" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 2px;">
          3rd Code (Category Items)
        </label>
        <select id="<?= $prefix ?>ItemSearchTier3" onchange="window.itemSearchController['<?= $prefix ?>']?.onTier3Change();" disabled
          style="width: 100%; height: 34px; font-size: 0.8rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 8px; background: #f1f5f9; opacity: 0.7;">
          <option value="">All 3rd Categories</option>
        </select>
      </div>


    </div>
  </div>

  <!-- Results Count Bar -->
  <div style="padding: 8px 20px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #475569;">
    <div>
      <span id="<?= $prefix ?>ItemSearchResultCountText">Showing <strong>0</strong> items</span>
    </div>
    <div id="<?= $prefix ?>ItemSearchPageInfo" style="font-weight: 600; color: #334155;">
      Page 1 of 1
    </div>
  </div>

  <!-- Results Table Container -->
  <div style="overflow-x: auto; max-height: 640px; overflow-y: auto;">
    <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
      <thead style="position: sticky; top: 0; background: #000000; color: #ffffff; z-index: 2; border-bottom: 2px solid #334155;">
        <tr>
          <th style="padding: 7px 10px; text-align: left; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 140px; white-space: nowrap;">ITEM CODE</th>
          <th style="padding: 7px 8px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 75px; white-space: nowrap;">1ST</th>
          <th style="padding: 7px 8px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 75px; white-space: nowrap;">2ND</th>
          <th style="padding: 7px 8px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 75px; white-space: nowrap;">3RD</th>
          <th style="padding: 7px 12px; text-align: left; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155;">ITEM DESCRIPTION &amp; SPECIFICATIONS</th>
          <th style="padding: 7px 8px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 70px; white-space: nowrap;">UOM</th>
          <th style="padding: 7px 10px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 130px; white-space: nowrap;">STATUS</th>
          <th style="padding: 7px 10px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 140px; white-space: nowrap;">ACTION</th>
        </tr>
      </thead>
      <tbody id="<?= $prefix ?>ItemSearchResultsBody">
        <tr>
          <td colspan="8" style="text-align: center; padding: 36px; color: #64748b; font-size: 0.88rem;">
            Loading master catalog items...
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Pagination Controls Footer -->
  <div style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <!-- Page Navigation Buttons -->
    <div id="<?= $prefix ?>ItemSearchPaginationButtons" style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
      <!-- Dynamically generated page buttons -->
    </div>

    <!-- Jump to Page Input -->
    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.78rem;">
      <span style="color: #64748b; font-weight: 600;">Jump to page:</span>
      <input type="number" id="<?= $prefix ?>ItemSearchJumpInput" min="1" max="1" placeholder="1"
        onkeydown="if(event.key==='Enter') window.itemSearchController['<?= $prefix ?>']?.jumpToPage();"
        style="width: 55px; height: 32px; text-align: center; font-size: 0.82rem; font-weight: 700; border: 1px solid #cbd5e1; border-radius: 5px; padding: 0 4px; outline: none; background: #ffffff; box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);">
      <button type="button" onclick="window.itemSearchController['<?= $prefix ?>']?.jumpToPage();" class="modern-page-btn"
        style="height: 32px; padding: 0 12px; font-size: 0.78rem; font-weight: 700; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; border-radius: 5px; cursor: pointer;">
        Go
      </button>
    </div>
  </div>

</div>
