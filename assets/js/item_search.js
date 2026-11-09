/**
 * FIFO Item Search Module Controller
 * Provides high-speed, live searchable catalog explorer for both User Portal and Admin Portal
 */

(function () {
  'use strict';

  window.itemSearchController = window.itemSearchController || {};

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  class ItemSearchModuleController {
    constructor(prefix) {
      this.prefix = prefix; // 'user' or 'admin'
      this.allItems = [];
      this.filteredItems = [];
      this.currentPage = 1;
      this.pageSize = 50;
      this.isLoading = false;
      this.debounceTimer = null;
      this.isInitialized = false;
    }

    /**
     * Initialize or load items
     */
    async init() {
      if (this.isInitialized && this.allItems.length > 0) {
        this.applyFiltersAndRender();
        return;
      }

      await this.reload();
      this.isInitialized = true;
    }

    /**
     * Reload items from server
     */
    async reload() {
      const tbody = document.getElementById(`${this.prefix}ItemSearchResultsBody`);
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" style="text-align: center; padding: 36px; color: #64748b; font-size: 0.88rem;">
              <div style="font-size: 1.5rem; margin-bottom: 6px;">⏳</div>
              Fetching master catalog items...
            </td>
          </tr>
        `;
      }

      this.isLoading = true;
      try {
        const res = await App.request('/api/code_management.php?action=list_master_items&limit=25000');
        if (res.ok && res.data && Array.isArray(res.data.items)) {
          // STRICT RULE: Pending process items must NEVER appear in item search
          this.allItems = res.data.items.filter(it => it.approval_status !== 'PENDING' && it.status !== 'PENDING');
        } else {
          this.allItems = [];
        }
      } catch (err) {
        console.error('Failed to load master items for item search:', err);
        this.allItems = [];
      } finally {
        this.isLoading = false;
      }

      this.applyFiltersAndRender();
    }

    /**
     * On search input typing with debounce
     */
    onSearchInput() {
      clearTimeout(this.debounceTimer);
      const query = (document.getElementById(`${this.prefix}ItemSearchQuery`)?.value || '').trim();
      const clearBtn = document.getElementById(`${this.prefix}ItemSearchClearBtn`);
      if (clearBtn) {
        clearBtn.style.display = query.length > 0 ? 'block' : 'none';
      }

      this.debounceTimer = setTimeout(() => {
        this.currentPage = 1;
        this.applyFiltersAndRender();
      }, 200);
    }

    /**
     * Clear search input
     */
    clearSearchInput() {
      const input = document.getElementById(`${this.prefix}ItemSearchQuery`);
      const clearBtn = document.getElementById(`${this.prefix}ItemSearchClearBtn`);
      if (input) input.value = '';
      if (clearBtn) clearBtn.style.display = 'none';
      this.currentPage = 1;
      this.applyFiltersAndRender();
    }

    /**
     * Page size change
     */
    onPageSizeChange(newSize) {
      this.pageSize = parseInt(newSize, 10) || 50;
      this.currentPage = 1;
      this.render();
    }

    /**
     * Cascading: Tier 1 changed
     */
    onTier1Change() {
      const t1Select = document.getElementById(`${this.prefix}ItemSearchTier1`);
      const t2Select = document.getElementById(`${this.prefix}ItemSearchTier2`);
      const t3Select = document.getElementById(`${this.prefix}ItemSearchTier3`);
      if (!t1Select || !t2Select || !t3Select) return;

      const t1Val = t1Select.value;
      const selectedOpt = t1Select.options[t1Select.selectedIndex];
      const t1DbId = selectedOpt ? selectedOpt.getAttribute('data-db-id') : null;
      const tierHierarchy = window.APP_DATA?.tierHierarchy || {};

      t2Select.innerHTML = '<option value="">All 2nd Sub Topics</option>';
      t3Select.innerHTML = '<option value="">All 3rd Categories</option>';
      t3Select.disabled = true;
      t3Select.style.background = '#f1f5f9';
      t3Select.style.opacity = '0.7';

      if (t1Val && t1DbId && tierHierarchy.tier2 && tierHierarchy.tier2[t1DbId]) {
        tierHierarchy.tier2[t1DbId].forEach(t2 => {
          const opt = document.createElement('option');
          opt.value = t2.code_val;
          opt.setAttribute('data-db-id', t2.id);
          opt.textContent = `${String(t2.code_val).padStart(2, '0')}. ${t2.name}`;
          t2Select.appendChild(opt);
        });
        t2Select.disabled = false;
        t2Select.style.background = '#ffffff';
        t2Select.style.opacity = '1';
      } else {
        t2Select.disabled = true;
        t2Select.style.background = '#f1f5f9';
        t2Select.style.opacity = '0.7';
      }

      this.currentPage = 1;
      this.applyFiltersAndRender();
    }

    /**
     * Cascading: Tier 2 changed
     */
    onTier2Change() {
      const t2Select = document.getElementById(`${this.prefix}ItemSearchTier2`);
      const t3Select = document.getElementById(`${this.prefix}ItemSearchTier3`);
      if (!t2Select || !t3Select) return;

      const t2Val = t2Select.value;
      const selectedOpt = t2Select.options[t2Select.selectedIndex];
      const t2DbId = selectedOpt ? selectedOpt.getAttribute('data-db-id') : null;
      const tierHierarchy = window.APP_DATA?.tierHierarchy || {};

      t3Select.innerHTML = '<option value="">All 3rd Categories</option>';

      if (t2Val && t2DbId && tierHierarchy.tier3 && tierHierarchy.tier3[t2DbId]) {
        tierHierarchy.tier3[t2DbId].forEach(t3 => {
          const opt = document.createElement('option');
          opt.value = t3.code_val;
          opt.setAttribute('data-db-id', t3.id);
          opt.textContent = `${String(t3.code_val).padStart(2, '0')}. ${t3.name}`;
          t3Select.appendChild(opt);
        });
        t3Select.disabled = false;
        t3Select.style.background = '#ffffff';
        t3Select.style.opacity = '1';
      } else {
        t3Select.disabled = true;
        t3Select.style.background = '#f1f5f9';
        t3Select.style.opacity = '0.7';
      }

      this.currentPage = 1;
      this.applyFiltersAndRender();
    }

    /**
     * Cascading: Tier 3 changed
     */
    onTier3Change() {
      this.currentPage = 1;
      this.applyFiltersAndRender();
    }

    /**
     * Status radio changed
     */
    onStatusChange() {
      this.currentPage = 1;
      this.applyFiltersAndRender();
    }

    /**
     * Reset all filters
     */
    resetFilters() {
      const qInput = document.getElementById(`${this.prefix}ItemSearchQuery`);
      const t1Select = document.getElementById(`${this.prefix}ItemSearchTier1`);
      const t2Select = document.getElementById(`${this.prefix}ItemSearchTier2`);
      const t3Select = document.getElementById(`${this.prefix}ItemSearchTier3`);
      const statusAll = document.querySelector(`input[name="${this.prefix}ItemSearchStatus"][value="all"]`);
      const clearBtn = document.getElementById(`${this.prefix}ItemSearchClearBtn`);

      if (qInput) qInput.value = '';
      if (clearBtn) clearBtn.style.display = 'none';
      if (t1Select) t1Select.value = '';
      if (t2Select) {
        t2Select.innerHTML = '<option value="">All 2nd Sub Topics</option>';
        t2Select.disabled = true;
        t2Select.style.background = '#f1f5f9';
        t2Select.style.opacity = '0.7';
      }
      if (t3Select) {
        t3Select.innerHTML = '<option value="">All 3rd Categories</option>';
        t3Select.disabled = true;
        t3Select.style.background = '#f1f5f9';
        t3Select.style.opacity = '0.7';
      }
      if (statusAll) statusAll.checked = true;

      this.currentPage = 1;
      this.applyFiltersAndRender();
    }

    /**
     * Core Filter Logic
     */
    applyFiltersAndRender() {
      const q = (document.getElementById(`${this.prefix}ItemSearchQuery`)?.value || '').trim().toLowerCase();
      const t1 = document.getElementById(`${this.prefix}ItemSearchTier1`)?.value;
      const t2 = document.getElementById(`${this.prefix}ItemSearchTier2`)?.value;
      const t3 = document.getElementById(`${this.prefix}ItemSearchTier3`)?.value;
      const statusRadio = document.querySelector(`input[name="${this.prefix}ItemSearchStatus"]:checked`)?.value || 'all';

      const t1Num = t1 !== '' && t1 !== null ? parseInt(t1, 10) : null;
      const t2Num = t2 !== '' && t2 !== null ? parseInt(t2, 10) : null;
      const t3Num = t3 !== '' && t3 !== null ? parseInt(t3, 10) : null;

      // Filter array in-memory
      this.filteredItems = this.allItems.filter(it => {
        // STRICT RULE: Pending process items must NEVER appear in item search
        if (it.approval_status === 'PENDING' || it.status === 'PENDING') return false;

        // Tier 1 check
        if (t1Num !== null && Number(it.tier1) !== t1Num) return false;
        // Tier 2 check
        if (t2Num !== null && Number(it.tier2) !== t2Num) return false;
        // Tier 3 check
        if (t3Num !== null && Number(it.tier3) !== t3Num) return false;

        // Status check
        const isMaster = Number(it.is_added_to_master) > 0;
        if (statusRadio === 'master' && !isMaster) return false;
        if (statusRadio === 'catalog' && isMaster) return false;

        // Keyword search check
        if (q) {
          const desc = (it.orig_description || it.item_name || '').toLowerCase();
          const specs = (it.specifications || '').toLowerCase();
          const masterName = (it.master_db_name || '').toLowerCase();
          const rawCode = String(it.raw_code || '').toLowerCase();
          const formattedCode = String(it.formatted_code || '').toLowerCase();
          const uom = String(it.orig_uom || it.uom || '').toLowerCase();

          // Match raw code without dots/hyphens
          const cleanQ = q.replace(/[^0-9a-zA-Z]/g, '');

          if (desc.includes(q) || specs.includes(q) || masterName.includes(q) || formattedCode.includes(q) || uom.includes(q)) {
            return true;
          }
          if (cleanQ && (rawCode.includes(cleanQ) || rawCode.replace(/[^0-9a-zA-Z]/g, '').includes(cleanQ))) {
            return true;
          }
          return false;
        }

        return true;
      });

      this.render();
    }

    /**
     * Render Table Page and Pagination
     */
    render() {
      const tbody = document.getElementById(`${this.prefix}ItemSearchResultsBody`);
      const countEl = document.getElementById(`${this.prefix}ItemSearchResultCountText`);
      const pageInfoEl = document.getElementById(`${this.prefix}ItemSearchPageInfo`);
      const pagBtnsEl = document.getElementById(`${this.prefix}ItemSearchPaginationButtons`);
      const jumpInput = document.getElementById(`${this.prefix}ItemSearchJumpInput`);
      if (!tbody) return;

      const totalItems = this.filteredItems.length;
      const totalPages = Math.max(1, Math.ceil(totalItems / this.pageSize));

      if (this.currentPage > totalPages) this.currentPage = totalPages;
      if (this.currentPage < 1) this.currentPage = 1;

      const startIdx = (this.currentPage - 1) * this.pageSize;
      const endIdx = Math.min(startIdx + this.pageSize, totalItems);
      const pageItems = this.filteredItems.slice(startIdx, endIdx);
      this.currentPageItems = pageItems;

      // Update counters
      const displayRange = totalItems > 0 ? `${startIdx + 1} - ${endIdx}` : '0 - 0';
      if (countEl) countEl.innerHTML = `Showing <strong>${displayRange}</strong> of <strong>${totalItems.toLocaleString()}</strong> items`;
      if (pageInfoEl) pageInfoEl.textContent = `Page ${this.currentPage} of ${totalPages}`;
      if (jumpInput) {
        jumpInput.max = totalPages;
        jumpInput.placeholder = String(this.currentPage);
      }

      // If empty
      if (pageItems.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" style="text-align: center; padding: 36px; color: #94a3b8; font-size: 0.88rem;">
              <div style="font-size: 1.5rem; margin-bottom: 6px;">📂</div>
              No items match your search filters. Try adjusting keywords or tier dropdowns.
            </td>
          </tr>
        `;
        if (pagBtnsEl) pagBtnsEl.innerHTML = '';
        return;
      }

      // Helper tier name resolution
      const tierHierarchy = window.APP_DATA?.tierHierarchy || {};
      const t1Map = new Map();
      (tierHierarchy.tier1 || []).forEach(t => t1Map.set(Number(t.code_val), t.name));

      const q = (document.getElementById(`${this.prefix}ItemSearchQuery`)?.value || '').trim();

      // Render Rows
      tbody.innerHTML = pageItems.map((it, idx) => {
        const t1Str = String(it.tier1 || 0).padStart(2, '0');
        const t2Str = String(it.tier2 || 0).padStart(2, '0');
        const t3Str = String(it.tier3 || 0).padStart(2, '0');
        const t4Str = String(it.tier4_seq || 0).padStart(3, '0');
        const rawCode = it.raw_code || (t1Str + t2Str + t3Str + t4Str);
        const formattedCode = `${t1Str}.${t2Str}.${t3Str}.${t4Str}`;
        const desc = it.orig_description || it.item_name || '';
        const specs = it.specifications || '';
        const uom = it.orig_uom || it.uom || 'NOS';
        const isMaster = Number(it.is_added_to_master) > 0;
        const bgRow = (idx % 2 === 0) ? '#ffffff' : '#f8fafc';

        const t1Name = t1Map.get(Number(it.tier1)) || '';

        // Highlight matching text in description
        let highlightedDesc = escapeHtml(desc);
        if (q && q.length >= 2) {
          const regex = new RegExp(`(${q.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')})`, 'gi');
          highlightedDesc = highlightedDesc.replace(regex, '<mark style="background: #fef08a; color: #854d0e; padding: 1px 2px; border-radius: 2px;">$1</mark>');
        }

        return `
          <tr style="border-bottom: 1px solid #e2e8f0; background: ${bgRow}; transition: background 0.15s ease;"
              onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='${bgRow}';">
            <!-- Formatted Code -->
            <td style="padding: 7px 10px; border: 1px solid #cbd5e1; white-space: nowrap;">
              <div>
                <span style="font-family: monospace; font-size: 0.85rem; font-weight: 800; color: #0f172a; background: #e2e8f0; padding: 2px 6px; border-radius: 3px; letter-spacing: 0.5px;">
                  ${escapeHtml(formattedCode)}
                </span>
              </div>
              <div style="font-family: monospace; font-size: 0.7rem; color: #94a3b8; margin-top: 2px;">
                Raw: ${escapeHtml(rawCode)}
              </div>
            </td>

            <!-- Tier 1 -->
            <td style="padding: 6px 8px; text-align: center; border: 1px solid #cbd5e1; font-family: monospace; font-size: 0.82rem; font-weight: 700; color: #1e293b;" title="${escapeHtml(t1Name)}">
              ${t1Str}
            </td>

            <!-- Tier 2 -->
            <td style="padding: 6px 8px; text-align: center; border: 1px solid #cbd5e1; font-family: monospace; font-size: 0.82rem; font-weight: 700; color: #1e293b;">
              ${t2Str}
            </td>

            <!-- Tier 3 -->
            <td style="padding: 6px 8px; text-align: center; border: 1px solid #cbd5e1; font-family: monospace; font-size: 0.82rem; font-weight: 700; color: #1e293b;">
              ${t3Str}
            </td>

            <!-- Item Description & Specs -->
            <td style="padding: 7px 12px; border: 1px solid #cbd5e1; color: #0f172a; line-height: 1.35;">
              <div style="font-weight: 700; font-size: 0.84rem; text-transform: uppercase;">
                ${highlightedDesc}
              </div>
              ${specs ? `<div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">${escapeHtml(specs)}</div>` : ''}
              ${t1Name ? `<div style="font-size: 0.7rem; color: #94a3b8; margin-top: 2px;">${escapeHtml(t1Name)}</div>` : ''}
            </td>

            <!-- UOM -->
            <td style="padding: 6px 8px; text-align: center; border: 1px solid #cbd5e1; font-weight: 700; font-size: 0.78rem; color: #475569; text-transform: uppercase;">
              ${escapeHtml(uom)}
            </td>

            <!-- Status -->
            <td style="padding: 6px 10px; text-align: center; border: 1px solid #cbd5e1; white-space: nowrap;">
              ${isMaster ? `
                <span style="display: inline-block; font-size: 0.72rem; font-weight: 800; color: #ffffff; background: #ea580c; border: 1px solid #c2410c; padding: 3px 8px; border-radius: 4px;">
                  Master Added
                </span>
              ` : `
                <span style="display: inline-block; font-size: 0.72rem; font-weight: 700; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 3px 8px; border-radius: 4px;">
                  Approved (Not in Master)
                </span>
              `}
            </td>

            <!-- Action Buttons -->
            <td style="padding: 6px 10px; text-align: center; border: 1px solid #cbd5e1; white-space: nowrap;">
              <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: center;">
                <button type="button" onclick="window.itemSearchController['${this.prefix}'].openViewItemModal(${idx});"
                  style="font-size: 0.74rem; font-weight: 700; padding: 4px 10px; border-radius: 4px; background: #1e3a8a; color: #ffffff; border: 1px solid #172554; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s ease;"
                  onmouseover="this.style.background='#1e40af';" onmouseout="this.style.background='#1e3a8a';"
                  title="View full item specifications, photo images, and unit price">
                  View
                </button>

                <button type="button" onclick="window.itemSearchController['${this.prefix}'].openGoogle('${escapeHtml(desc)}');"
                  style="font-size: 0.74rem; font-weight: 700; padding: 4px 10px; border-radius: 4px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; cursor: pointer;"
                  title="Search item datasheet on Google">
                  🌐 Google
                </button>

                ${isMaster ? `
                  <button type="button" onclick="window.itemSearchController['${this.prefix}'].handleMasterAdd(${idx});"
                    style="font-size: 0.74rem; font-weight: 700; padding: 4px 10px; border-radius: 4px; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; cursor: pointer;"
                    title="Add code to station inventory">
                    ➕ Add
                  </button>
                ` : `
                  <button type="button" onclick="window.itemSearchController['${this.prefix}'].openItemRequestModal(${idx});"
                    style="font-size: 0.74rem; font-weight: 700; padding: 4px 12px; border-radius: 4px; background: #0284c7; color: #ffffff; border: 1px solid #0369a1; cursor: pointer;"
                    title="Request this item code for your power station">
                    Request
                  </button>
                `}
              </div>
            </td>
          </tr>
        `;
      }).join('');

      this.currentPageItems = pageItems;

      // Render Pagination Buttons
      this.renderPaginationButtons(totalPages);
    }

    /**
     * Render Pagination Controls
     */
    renderPaginationButtons(totalPages) {
      const pagBtnsEl = document.getElementById(`${this.prefix}ItemSearchPaginationButtons`);
      if (!pagBtnsEl) return;

      if (totalPages <= 1) {
        pagBtnsEl.innerHTML = '';
        return;
      }

      let html = '';
      const cur = this.currentPage;

      const baseBtnStyle = 'display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; font-size: 0.82rem; font-weight: 600; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; border-radius: 5px; outline: none; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04); text-decoration: none;';
      const activeStyle = baseBtnStyle + ' background: #650b2b; color: #ffffff; border: 1px solid #4a071e; font-weight: 800; cursor: default; box-shadow: 0 2px 4px rgba(101,11,43,0.3);';
      const normalStyle = baseBtnStyle + ' background: #ffffff; color: #334155; border: 1px solid #cbd5e1; cursor: pointer;';
      const disabledStyle = baseBtnStyle + ' background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; opacity: 0.4; cursor: not-allowed; box-shadow: none;';
      const hoverEvents = 'onmouseover="if(!this.disabled && !this.classList.contains(\'active\')) { this.style.backgroundColor=\'#f1f5f9\'; this.style.borderColor=\'#94a3b8\'; this.style.color=\'#0f172a\'; }" onmouseout="if(!this.disabled && !this.classList.contains(\'active\')) { this.style.backgroundColor=\'#ffffff\'; this.style.borderColor=\'#cbd5e1\'; this.style.color=\'#334155\'; }"';

      // Prev Button
      const prevDisabled = cur <= 1;
      html += `
        <button type="button" onclick="window.itemSearchController['${this.prefix}'].changePage(${cur - 1});"
          ${prevDisabled ? 'disabled' : ''}
          class="modern-page-btn" style="${prevDisabled ? disabledStyle : normalStyle}"
          ${hoverEvents}
          title="Previous Page">
          ‹ Prev
        </button>
      `;

      // Window of pages
      const windowSize = 5;
      let startP = Math.max(1, cur - Math.floor(windowSize / 2));
      let endP = Math.min(totalPages, startP + windowSize - 1);
      if (endP - startP + 1 < windowSize) {
        startP = Math.max(1, endP - windowSize + 1);
      }

      if (startP > 1) {
        html += `<button type="button" onclick="window.itemSearchController['${this.prefix}'].changePage(1);" class="modern-page-btn" style="${normalStyle}" ${hoverEvents}>1</button>`;
        if (startP > 2) html += `<span class="modern-page-ellipsis" style="display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:32px; color:#94a3b8; font-weight:700;">...</span>`;
      }

      for (let p = startP; p <= endP; p++) {
        const isActive = p === cur;
        html += `
          <button type="button" onclick="window.itemSearchController['${this.prefix}'].changePage(${p});"
            class="modern-page-btn ${isActive ? 'active' : ''}" style="${isActive ? activeStyle : normalStyle}"
            ${isActive ? '' : hoverEvents}>
            ${p}
          </button>
        `;
      }

      if (endP < totalPages) {
        if (endP < totalPages - 1) html += `<span class="modern-page-ellipsis" style="display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:32px; color:#94a3b8; font-weight:700;">...</span>`;
        html += `<button type="button" onclick="window.itemSearchController['${this.prefix}'].changePage(${totalPages});" class="modern-page-btn" style="${normalStyle}" ${hoverEvents}>${totalPages}</button>`;
      }

      // Next Button
      const nextDisabled = cur >= totalPages;
      html += `
        <button type="button" onclick="window.itemSearchController['${this.prefix}'].changePage(${cur + 1});"
          ${nextDisabled ? 'disabled' : ''}
          class="modern-page-btn" style="${nextDisabled ? disabledStyle : normalStyle}"
          ${hoverEvents}
          title="Next Page">
          Next ›
        </button>
      `;

      pagBtnsEl.innerHTML = html;
    }

    /**
     * Change active page
     */
    changePage(p) {
      const totalPages = Math.max(1, Math.ceil(this.filteredItems.length / this.pageSize));
      if (p < 1) p = 1;
      if (p > totalPages) p = totalPages;
      this.currentPage = p;
      this.render();

      // Smooth scroll back to table top
      const tableContainer = document.querySelector(`#${this.prefix}ItemSearchResultsBody`)?.closest('div[style*="overflow-y"]');
      if (tableContainer) tableContainer.scrollTop = 0;
    }

    /**
     * Jump to page from input
     */
    jumpToPage() {
      const input = document.getElementById(`${this.prefix}ItemSearchJumpInput`);
      if (!input) return;
      const p = parseInt(input.value, 10);
      if (!isNaN(p)) {
        this.changePage(p);
      }
    }

    /**
     * Copy code to clipboard
     */
    copyCode(code) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(() => {
          App.toast(`Copied code: ${code}`, 'success');
        }).catch(() => {
          this.fallbackCopy(code);
        });
      } else {
        this.fallbackCopy(code);
      }
    }

    fallbackCopy(code) {
      const ta = document.createElement('textarea');
      ta.value = code;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        App.toast(`Copied code: ${code}`, 'success');
      } catch (e) {
        prompt('Copy code manually:', code);
      }
      document.body.removeChild(ta);
    }

    /**
     * Open Google Search for item
     */
    openGoogle(desc) {
      if (!desc) return;
      const query = encodeURIComponent(`${desc} datasheet specifications`);
      window.open(`https://www.google.com/search?q=${query}`, '_blank');
    }

    /**
     * Switch to Add Code sub-tab and optionally prefill tier
     */
    goToAddCode(t1 = 0, t2 = 0, t3 = 0) {
      if (this.prefix === 'user') {
        if (typeof window.switchUserCodeSubTab === 'function') {
          window.switchUserCodeSubTab('add');
        }
        if (t1 > 0) {
          const simpleT1 = document.getElementById('simpleTier1');
          if (simpleT1) {
            simpleT1.value = t1;
            if (typeof window.onSimpleTier1Change === 'function') {
              window.onSimpleTier1Change();
            }
          }
        }
      } else {
        // Admin portal
        if (typeof window.switchAdminCodeSubTab === 'function') {
          window.switchAdminCodeSubTab('add');
        }
        if (t1 > 0) {
          const filterT1 = document.getElementById('excelTreeTier1Filter');
          if (filterT1) {
            filterT1.value = t1;
            if (typeof window.onExcelTreeFilterChange === 'function') {
              window.onExcelTreeFilterChange();
            }
          }
        }
      }
    }

    /**
     * Handle click on ➕ Add button for Master Added item
     */
    handleMasterAdd(idx) {
      const it = this.currentPageItems?.[idx];
      if (!it) return;

      const masterId = it.master_id || it.id;
      const t1Str = String(it.tier1 || 0).padStart(2, '0');
      const t2Str = String(it.tier2 || 0).padStart(2, '0');
      const t3Str = String(it.tier3 || 0).padStart(2, '0');
      const t4Str = String(it.tier4_seq || 0).padStart(3, '0');
      const formattedCode = it.formatted_code || `${t1Str}.${t2Str}.${t3Str}.${t4Str}`;
      const desc = it.master_db_name || it.item_name || it.orig_description || '';

      // If openImportModal is available (Station inventory context), open modal to add directly to station
      if (typeof window.openImportModal === 'function' && masterId && Number(masterId) > 0) {
        window.openImportModal(Number(masterId), formattedCode, encodeURIComponent(desc));
        return;
      }

      // Otherwise navigate to Add Code sub-tab with categories preselected
      this.goToAddCode(it.tier1 || 0, it.tier2 || 0, it.tier3 || 0);
    }

    /**
     * Open Request Modal for Approved (Not in Master) item
     */
    openItemRequestModal(idx) {
      const it = this.currentPageItems?.[idx];
      if (!it) return;

      const t1 = parseInt(it.tier1 || 0, 10);
      const t2 = parseInt(it.tier2 || 0, 10);
      const t3 = parseInt(it.tier3 || 0, 10);
      const t4 = parseInt(it.tier4_seq || 0, 10);
      const desc = it.orig_description || it.item_name || '';
      const specs = it.specifications || '';
      const uom = it.orig_uom || it.uom || 'Nos';
      const formattedCode = `${t1}-${String(t2).padStart(2, '0')}-${String(t3).padStart(2, '0')}-${String(t4).padStart(3, '0')}`;

      const t1Input = document.getElementById('modalReqTier1');
      const t2Input = document.getElementById('modalReqTier2');
      const t3Input = document.getElementById('modalReqTier3');
      const t4Input = document.getElementById('modalReqTier4');
      const codeLabel = document.getElementById('modalReqFormattedCode');
      const branchLabel = document.getElementById('modalReqBranchLabel');
      const nameInput = document.getElementById('modalReqItemName');
      const specsInput = document.getElementById('modalReqSpecs');
      const uomSelect = document.getElementById('modalReqUom');

      if (t1Input) t1Input.value = t1;
      if (t2Input) t2Input.value = t2;
      if (t3Input) t3Input.value = t3;
      if (t4Input) t4Input.value = t4;
      if (codeLabel) codeLabel.textContent = formattedCode;
      if (nameInput) nameInput.value = desc.toUpperCase();
      if (specsInput) specsInput.value = specs;

      if (uomSelect) {
        const cleanUom = uom.trim().toLowerCase();
        for (let opt of uomSelect.options) {
          if (opt.value.toLowerCase() === cleanUom || opt.text.toLowerCase().startsWith(cleanUom)) {
            uomSelect.value = opt.value;
            break;
          }
        }
      }

      const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
      const t1Obj = (tiers.tier1 || []).find(t => Number(t.code_val) === t1);
      let t2Obj = null;
      if (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) {
        t2Obj = tiers.tier2[t1Obj.id].find(t => Number(t.code_val) === t2);
      }
      let t3Obj = null;
      if (t2Obj && tiers.tier3 && tiers.tier3[t2Obj.id]) {
        t3Obj = tiers.tier3[t2Obj.id].find(t => Number(t.code_val) === t3);
      }
      const p1 = document.getElementById('modalReqPathT1');
      const p2 = document.getElementById('modalReqPathT2');
      const p3 = document.getElementById('modalReqPathT3');
      if (p1) { p1.textContent = t1Obj ? `${String(t1).padStart(2,'0')} - ${t1Obj.name}` : `Tier ${t1}`; p1.title = p1.textContent; }
      if (p2) { p2.textContent = t2Obj ? `${String(t2).padStart(2,'0')} - ${t2Obj.name}` : `Tier ${t2}`; p2.title = p2.textContent; }
      if (p3) { p3.textContent = t3Obj ? `${String(t3).padStart(2,'0')} - ${t3Obj.name}` : `Tier ${t3}`; p3.title = p3.textContent; }
      if (branchLabel) branchLabel.innerHTML = branchParts.join(' &bull; ');

      if (typeof window.openModal === 'function') {
        window.openModal('requestNewCodeModal');
      } else {
        const modal = document.getElementById('requestNewCodeModal');
        if (modal) {
          modal.style.display = 'flex';
          modal.classList.add('active');
        }
      }
      setTimeout(() => {
        document.getElementById('modalReqItemName')?.focus();
        if (typeof window.updateModalSuggestions === 'function') {
          window.updateModalSuggestions();
        }
      }, 100);
    }

    /**
     * Open View Item Specification & Photo Modal
     */
    openViewItemModal(idx) {
      const it = (this.currentPageItems && this.currentPageItems[idx]) || this.filteredItems?.[(this.currentPage - 1) * this.pageSize + idx] || this.filteredItems?.[idx];
      if (!it) {
        console.warn('Item not found for index:', idx);
        return;
      }

      window._currentViewingItem = it;
      const isCodeAdmin = Boolean(window.APP_DATA?.isCodeAdmin || window.APP_DATA?.isAdmin || window.APP_DATA?.userRole === 'code_admin' || window.APP_DATA?.userRole === 'super_admin');

      const t1 = parseInt(it.tier1 || 0, 10);
      const t2 = parseInt(it.tier2 || 0, 10);
      const t3 = parseInt(it.tier3 || 0, 10);
      const t4 = parseInt(it.tier4_seq || 0, 10);
      const formattedCode = `${String(t1).padStart(2,'0')}.${String(t2).padStart(2,'0')}.${String(t3).padStart(2,'0')}.${String(t4).padStart(3,'0')}`;
      const desc = it.orig_description || it.item_name || '';
      const specs = it.specifications || '';
      const uom = it.orig_uom || it.uom || 'NOS';
      const isMaster = Number(it.is_added_to_master) > 0;

      // Tier Names
      const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
      const t1Obj = (tiers.tier1 || []).find(t => Number(t.code_val) === t1);
      let t2Obj = null;
      if (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) {
        t2Obj = tiers.tier2[t1Obj.id].find(t => Number(t.code_val) === t2);
      }
      let t3Obj = null;
      if (t2Obj && tiers.tier3 && tiers.tier3[t2Obj.id]) {
        t3Obj = tiers.tier3[t2Obj.id].find(t => Number(t.code_val) === t3);
      }

      const codeEl = document.getElementById('viewModalFormattedCode');
      const statusEl = document.getElementById('viewModalStatusBadge');
      const nameEl = document.getElementById('viewModalItemName');
      const specsEl = document.getElementById('viewModalSpecs');
      const tier1El = document.getElementById('viewModalTier1');
      const tier2El = document.getElementById('viewModalTier2');
      const tier3El = document.getElementById('viewModalTier3');
      const uomEl = document.getElementById('viewModalUom');
      const googleBtn = document.getElementById('viewModalGoogleBtn');
      const actionBtn = document.getElementById('viewModalActionBtn');

      if (codeEl) codeEl.textContent = formattedCode;
      if (statusEl) {
        if (isMaster) {
          statusEl.textContent = 'MASTER ADDED';
          statusEl.style.background = '#ffedd5';
          statusEl.style.color = '#c2410c';
          statusEl.style.border = '1px solid #fed7aa';
        } else {
          statusEl.textContent = 'APPROVED (NOT IN MASTER)';
          statusEl.style.background = '#e0f2fe';
          statusEl.style.color = '#0284c7';
          statusEl.style.border = '1px solid #bae6fd';
        }
      }

      if (nameEl) nameEl.textContent = desc.toUpperCase();
      if (specsEl) specsEl.textContent = specs || 'No additional technical specifications provided.';
      if (tier1El) tier1El.textContent = t1Obj ? `${String(t1).padStart(2,'0')} - ${t1Obj.name}` : `Tier ${t1}`;
      if (tier2El) tier2El.textContent = t2Obj ? `${String(t2).padStart(2,'0')} - ${t2Obj.name}` : `Tier ${t2}`;
      if (tier3El) tier3El.textContent = t3Obj ? `${String(t3).padStart(2,'0')} - ${t3Obj.name}` : `Tier ${t3}`;
      if (uomEl) uomEl.textContent = uom.toUpperCase();

      // Render Price (Dark type if no price added)
      this.renderViewItemPrice(it);

      // Render Image Slots (Max 2 images, No Image placeholder if empty)
      this.renderViewItemImages(it);

      // Setup Google Search
      if (googleBtn) {
        googleBtn.onclick = () => {
          this.openGoogle(desc);
        };
      }

      // Setup Action Button
      if (actionBtn) {
        if (!isMaster) {
          if (it.approval_status === 'PENDING' && it.request_id) {
            actionBtn.style.display = 'inline-block';
            actionBtn.textContent = '✏️ Edit Request';
            actionBtn.style.background = '#0284c7';
            actionBtn.style.color = '#ffffff';
            actionBtn.style.border = '1px solid #0369a1';
            actionBtn.onclick = () => {
              if (typeof window.closeViewItemModal === 'function') window.closeViewItemModal();
              if (typeof window.openEditCodeRequestModal === 'function') window.openEditCodeRequestModal(it.request_id || it);
            };
          } else {
            actionBtn.style.display = 'inline-block';
            actionBtn.textContent = 'Request for Station';
            actionBtn.style.background = '#0284c7';
            actionBtn.style.color = '#ffffff';
            actionBtn.style.border = '1px solid #0369a1';
            actionBtn.onclick = () => {
              if (typeof window.closeViewItemModal === 'function') window.closeViewItemModal();
              this.openItemRequestModal(idx);
            };
          }
        } else {
          actionBtn.style.display = 'none';
        }
      }

      // Open Modal
      const modal = document.getElementById('viewItemDetailsModal');
      if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
      }
      if (typeof window.openModal === 'function') {
        window.openModal('viewItemDetailsModal');
      }
    }

    /**
     * Render Price Card (Shows DARK TYPE when no price added)
     */
    renderViewItemPrice(it) {
      const priceContainer = document.getElementById('viewModalPriceContainer');
      const editBtn = document.getElementById('viewModalEditPriceBtn');
      const editForm = document.getElementById('viewModalPriceEditForm');
      if (editForm) editForm.style.display = 'none';

      const isCodeAdmin = Boolean(window.APP_DATA?.isCodeAdmin || window.APP_DATA?.isAdmin || window.APP_DATA?.userRole === 'code_admin' || window.APP_DATA?.userRole === 'super_admin');
      if (editBtn) editBtn.style.display = isCodeAdmin ? 'inline-block' : 'none';

      if (!priceContainer) return;

      const hasPrice = it.unit_price !== null && it.unit_price !== undefined && it.unit_price !== '' && Number(it.unit_price) > 0;

      if (hasPrice) {
        const numVal = Number(it.unit_price);
        priceContainer.innerHTML = `
          <div style="display: flex; align-items: baseline; gap: 8px;">
            <span style="font-size: 1.35rem; font-weight: 800; color: #166534; letter-spacing: 0.5px;">
              LKR ${numVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
            <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">(Per ${escapeHtml(it.orig_uom || it.uom || 'unit')})</span>
          </div>
        `;
      } else {
        // DARK TYPE AS USER REQUESTED:
        priceContainer.innerHTML = `
          <div style="display: inline-flex; align-items: center; gap: 8px; background: #1e3a8a; color: #f8fafc; border: 1.5px solid #2563eb; padding: 6px 14px; border-radius: 6px; font-weight: 800; font-size: 0.88rem; letter-spacing: 0.5px; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #93c5fd;"></span>
            <span>NO PRICE ADDED</span>
            <span style="font-size: 0.7rem; color: #bfdbfe; font-weight: 500;">(Price not yet assigned in catalog)</span>
          </div>
        `;
      }
    }

    /**
     * Render Image Slots (Maximum 2 images, clean NO IMAGE graphic if empty)
     */
    renderViewItemImages(it) {
      const slot1 = document.getElementById('viewModalImg1Slot');
      const slot2 = document.getElementById('viewModalImg2Slot');
      const isCodeAdmin = Boolean(window.APP_DATA?.isCodeAdmin || window.APP_DATA?.isAdmin || window.APP_DATA?.userRole === 'code_admin' || window.APP_DATA?.userRole === 'super_admin');

      const renderSlot = (slotEl, slotNum, imgUrl, title, subtitle) => {
        if (!slotEl) return;
        if (imgUrl) {
          slotEl.innerHTML = `
            <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 0.68rem; font-weight: 800; color: #0284c7; text-transform: uppercase; background: #e0f2fe; padding: 2px 8px; border-radius: 12px; border: 1px solid #bae6fd;">
                ${title}
              </span>
              ${isCodeAdmin ? `
                <div style="display: flex; gap: 4px;">
                  <button type="button" onclick="triggerItemImageUpload(${slotNum});" style="background: none; border: none; font-size: 0.68rem; color: #0284c7; cursor: pointer; font-weight: 700;">Change</button>
                  <span style="color: #cbd5e1;">|</span>
                  <button type="button" onclick="deleteItemImage(${slotNum});" style="background: none; border: none; font-size: 0.68rem; color: #dc2626; cursor: pointer; font-weight: 700;">Delete</button>
                </div>
              ` : ''}
            </div>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; width: 100%; min-height: 140px; cursor: pointer;" onclick="window.open('${escapeHtml(imgUrl)}', '_blank');">
              <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(it.item_name || 'Item')}"
                style="max-height: 140px; max-width: 100%; object-fit: contain; border-radius: 4px; transition: transform 0.2s;"
                onmouseover="this.style.transform='scale(1.03)';" onmouseout="this.style.transform='scale(1)';"
                onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\\'color:#dc2626; font-size:0.75rem; text-align:center;\\'>Failed to load image</div>';">
            </div>
            <div style="font-size: 0.68rem; color: #64748b; margin-top: 4px;">🔍 Click image to view full size</div>
          `;
        } else {
          // NO IMAGE (AS USER REQUESTED: "show no image with the imaage top. if add show image .")
          slotEl.innerHTML = `
            <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; background: #f1f5f9; padding: 2px 8px; border-radius: 12px; border: 1px solid #cbd5e1;">
                ${title}
              </span>
            </div>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 14px 10px; text-align: center;">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: #f1f5f9; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #94a3b8; margin-bottom: 6px;">
                📷
              </div>
              <strong style="font-size: 0.78rem; color: #475569; letter-spacing: 0.5px;">NO IMAGE AVAILABLE</strong>
              <span style="font-size: 0.68rem; color: #94a3b8; margin-top: 2px;">${subtitle}</span>
              ${isCodeAdmin ? `
                <button type="button" onclick="triggerItemImageUpload(${slotNum});" class="btn btn-sm"
                  style="margin-top: 8px; font-size: 0.7rem; font-weight: 700; background: #ffffff; color: #0f172a; border: 1px solid #cbd5e1; padding: 3px 10px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                  📤 Upload Photo
                </button>
              ` : ''}
            </div>
          `;
        }
      };

      renderSlot(slot1, 1, it.image_url_1, 'PRIMARY PRODUCT PHOTO (IMAGE 1)', 'No primary photo uploaded');
      renderSlot(slot2, 2, it.image_url_2, 'TECHNICAL DRAWING / PHOTO (IMAGE 2)', 'No secondary drawing uploaded');
    }
  }

  // Register instances
  window.itemSearchController['user'] = new ItemSearchModuleController('user');
  window.itemSearchController['admin'] = new ItemSearchModuleController('admin');

  // Global Close View Modal Function
  window.closeViewItemModal = function () {
    const modal = document.getElementById('viewItemDetailsModal');
    if (modal) {
      modal.classList.remove('active');
      modal.style.display = 'none';
      modal.style.opacity = '0';
      modal.style.visibility = 'hidden';
    }
    if (typeof window.closeModal === 'function') {
      window.closeModal('viewItemDetailsModal');
    }
  };

  // Close modal when pressing Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.key === 'Esc') {
      const modal = document.getElementById('viewItemDetailsModal');
      if (modal && (modal.classList.contains('active') || modal.style.display === 'flex')) {
        window.closeViewItemModal();
      }
    }
  });

  // Global Image & Price Handlers
  window.triggerItemImageUpload = function (slot) {
    const input = document.getElementById(`viewItemHiddenFileInput${slot}`);
    if (input) input.click();
  };

  window.handleItemImageFileSelected = async function (slot, input) {
    if (!input.files || !input.files[0]) return;
    const item = window._currentViewingItem;
    if (!item) return;
    const masterId = item.master_id || item.id;
    if (!masterId) {
      App.toast('Cannot upload image: Item must be in Master Database.', 'warning');
      return;
    }

    const file = input.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_item_media');
    formData.append('master_id', masterId);
    formData.append(`image_${slot}`, file);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    formData.append('_csrf_token', csrfToken);

    try {
      App.toast(`Uploading Image ${slot}...`, 'info');
      const res = await fetch('/api/code_management.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
      });
      const data = await res.json();
      if (data.success && data.item) {
        App.toast(`Image ${slot} uploaded successfully!`, 'success');
        item[`image_url_${slot}`] = data.item[`image_url_${slot}`];
        if (window.itemSearchController?.['user']?.renderViewItemImages) {
          window.itemSearchController['user'].renderViewItemImages(item);
        }
        if (window.itemSearchController?.['admin']?.renderViewItemImages) {
          window.itemSearchController['admin'].renderViewItemImages(item);
        }
      } else {
        App.toast(data.message || 'Image upload failed', 'error');
      }
    } catch (e) {
      App.toast('Network error uploading image: ' + e.message, 'error');
    } finally {
      input.value = '';
    }
  };

  window.deleteItemImage = async function (slot) {
    if (!confirm(`Are you sure you want to remove Image ${slot}?`)) return;
    const item = window._currentViewingItem;
    if (!item) return;
    const masterId = item.master_id || item.id;
    if (!masterId) return;

    const formData = new FormData();
    formData.append('action', 'upload_item_media');
    formData.append('master_id', masterId);
    formData.append(`delete_image_${slot}`, '1');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    formData.append('_csrf_token', csrfToken);

    try {
      const res = await fetch('/api/code_management.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
      });
      const data = await res.json();
      if (data.success && data.item) {
        App.toast(`Image ${slot} removed.`, 'success');
        item[`image_url_${slot}`] = null;
        if (window.itemSearchController?.['user']?.renderViewItemImages) {
          window.itemSearchController['user'].renderViewItemImages(item);
        }
        if (window.itemSearchController?.['admin']?.renderViewItemImages) {
          window.itemSearchController['admin'].renderViewItemImages(item);
        }
      }
    } catch (e) {
      App.toast('Error removing image', 'error');
    }
  };

  window.toggleViewItemPriceInput = function (show = true) {
    const editForm = document.getElementById('viewModalPriceEditForm');
    const input = document.getElementById('viewModalPriceInput');
    if (!editForm) return;
    if (show) {
      editForm.style.display = 'flex';
      const cur = window._currentViewingItem?.unit_price;
      if (input) {
        input.value = cur !== null && cur !== undefined ? cur : '';
        input.focus();
      }
    } else {
      editForm.style.display = 'none';
    }
  };

  window.saveViewItemPrice = async function () {
    const item = window._currentViewingItem;
    if (!item) return;
    const masterId = item.master_id || item.id;
    if (!masterId) {
      App.toast('Cannot set price: Item must be added to Master Database first.', 'warning');
      return;
    }

    const input = document.getElementById('viewModalPriceInput');
    const priceVal = (input?.value || '').trim();

    const formData = new FormData();
    formData.append('action', 'upload_item_media');
    formData.append('master_id', masterId);
    formData.append('unit_price', priceVal);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    formData.append('_csrf_token', csrfToken);

    try {
      const res = await fetch('/api/code_management.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
      });
      const data = await res.json();
      if (data.success && data.item) {
        App.toast('Price updated successfully!', 'success');
        item.unit_price = data.item.unit_price;
        if (window.itemSearchController?.['user']?.renderViewItemPrice) {
          window.itemSearchController['user'].renderViewItemPrice(item);
        }
        if (window.itemSearchController?.['admin']?.renderViewItemPrice) {
          window.itemSearchController['admin'].renderViewItemPrice(item);
        }
      } else {
        App.toast(data.message || 'Failed to update price', 'error');
      }
    } catch (e) {
      App.toast('Error updating price: ' + e.message, 'error');
    }
  };

  // Sub-tab Switchers
  window.switchUserCodeSubTab = function (subTab) {
    if (subTab === 'search') {
      const trigger = document.querySelector('[data-user-tab="tab-user-code-item-search"]');
      if (trigger) trigger.click();
      window.itemSearchController['user']?.init();
    } else {
      const trigger = document.querySelector('[data-user-tab="tab-user-code-roles"]');
      if (trigger) trigger.click();
    }
  };

  window.switchAdminCodeSubTab = function (subTab) {
    const searchPane = document.getElementById('adminCodeSubPaneSearch');
    const addPane = document.getElementById('adminCodeSubPaneAdd');
    const btnSearch = document.getElementById('adminCodeSubBtnSearch');
    const btnAdd = document.getElementById('adminCodeSubBtnAdd');
    if (!searchPane || !addPane) return;

    if (subTab === 'search') {
      searchPane.style.display = 'block';
      addPane.style.display = 'none';
      if (btnSearch) {
        btnSearch.style.background = '#650b2b';
        btnSearch.style.color = '#ffffff';
        btnSearch.style.borderColor = '#4a071e';
        btnSearch.style.fontWeight = '800';
      }
      if (btnAdd) {
        btnAdd.style.background = '#ffffff';
        btnAdd.style.color = '#334155';
        btnAdd.style.borderColor = '#cbd5e1';
        btnAdd.style.fontWeight = '700';
      }
      window.itemSearchController['admin']?.init();
    } else {
      searchPane.style.display = 'none';
      addPane.style.display = 'block';
      if (btnAdd) {
        btnAdd.style.background = '#650b2b';
        btnAdd.style.color = '#ffffff';
        btnAdd.style.borderColor = '#4a071e';
        btnAdd.style.fontWeight = '800';
      }
      if (btnSearch) {
        btnSearch.style.background = '#ffffff';
        btnSearch.style.color = '#334155';
        btnSearch.style.borderColor = '#cbd5e1';
        btnSearch.style.fontWeight = '700';
      }
    }
  };

  // Auto-init on DOMContentLoaded if visible
  document.addEventListener('DOMContentLoaded', () => {
    const userPane = document.getElementById('tab-user-code-item-search');
    if (userPane && userPane.classList.contains('active')) {
      window.itemSearchController['user']?.init();
    }
    const adminSearchPane = document.getElementById('adminCodeSubPaneSearch');
    if (adminSearchPane && adminSearchPane.style.display === 'block') {
      window.itemSearchController['admin']?.init();
    }
  });

})();
