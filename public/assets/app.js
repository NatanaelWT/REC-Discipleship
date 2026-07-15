(function () {
  function filterTable(control) {
    const tableId = control.getAttribute('data-filter');
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    const controls = document.querySelectorAll('[data-filter="' + tableId + '"]');
    let q = '';
    let statusFilter = 'all';
    let peopleStatusFilter = 'all';
    let recapProgressFilter = 'all';
    let memberFeedbackProgressFilter = 'all';
    let memberFeedbackSessionFilter = 'all';

    controls.forEach((node) => {
      const role = String(node.getAttribute('data-filter-role') || '').toLowerCase();
      if (role === 'status') {
        statusFilter = String(node.value || 'all').toLowerCase();
      } else if (role === 'people-status') {
        peopleStatusFilter = String(node.value || 'all').toLowerCase();
      } else if (role === 'recap-progress') {
        recapProgressFilter = String(node.value || 'all').toLowerCase();
      } else if (role === 'member-feedback-progress') {
        memberFeedbackProgressFilter = String(node.value || 'all').toLowerCase();
      } else if (role === 'member-feedback-session') {
        memberFeedbackSessionFilter = String(node.value || 'all').toLowerCase();
      } else {
        q = String(node.value || '').toLowerCase();
      }
    });

    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      const rowStatus = String(row.getAttribute('data-group-status') || '').toLowerCase();
      const rowPeopleFilter = String(row.getAttribute('data-people-filter') || '').toLowerCase();
      const rowPeopleFilterTokens = rowPeopleFilter.split(/[\s,|]+/).filter(Boolean);
      const rowRecapProgress = String(row.getAttribute('data-recap-progress') || '').toLowerCase();
      const rowMemberFeedbackProgress = String(row.getAttribute('data-member-feedback-progress') || '').toLowerCase();
      const rowMemberFeedbackSession = String(row.getAttribute('data-member-feedback-session') || '').toLowerCase();
      const rowMemberFeedbackSessionTokens = rowMemberFeedbackSession.split(/[\s,|]+/).filter(Boolean);
      const matchesSearch = text.includes(q);
      const matchesStatus = statusFilter === 'all' || rowStatus === statusFilter;
      const matchesPeopleStatus = peopleStatusFilter === 'all' || rowPeopleFilterTokens.includes(peopleStatusFilter) || rowPeopleFilter === peopleStatusFilter;
      const matchesRecapProgress = recapProgressFilter === 'all' || rowRecapProgress === recapProgressFilter;
      const matchesMemberFeedbackProgress = memberFeedbackProgressFilter === 'all' || rowMemberFeedbackProgress === memberFeedbackProgressFilter;
      const matchesMemberFeedbackSession = memberFeedbackSessionFilter === 'all'
        || rowMemberFeedbackSession === memberFeedbackSessionFilter
        || rowMemberFeedbackSessionTokens.includes(memberFeedbackSessionFilter);
      row.style.display = matchesSearch
        && matchesStatus
        && matchesPeopleStatus
        && matchesRecapProgress
        && matchesMemberFeedbackProgress
        && matchesMemberFeedbackSession
          ? ''
          : 'none';
    });

    if (tableId === 'groups-dashboard-table') {
      const statNodes = {
        total: document.querySelector('[data-groups-stat="total"]'),
        dg1: document.querySelector('[data-groups-stat="dg1"]'),
        dg2: document.querySelector('[data-groups-stat="dg2"]'),
        dg3: document.querySelector('[data-groups-stat="dg3"]')
      };
      const visibleRows = Array.from(rows).filter((row) => row.style.display !== 'none');
      let total = 0;
      let dg1 = 0;
      let dg2 = 0;
      let dg3 = 0;

      visibleRows.forEach((row) => {
        total += 1;
        const rowProgress = String(row.getAttribute('data-group-progress') || '').toLowerCase();
        if (rowProgress === 'dg1') {
          dg1 += 1;
        } else if (rowProgress === 'dg2') {
          dg2 += 1;
        } else if (rowProgress === 'dg3') {
          dg3 += 1;
        }
      });

      if (statNodes.total) statNodes.total.textContent = String(total);
      if (statNodes.dg1) statNodes.dg1.textContent = String(dg1);
      if (statNodes.dg2) statNodes.dg2.textContent = String(dg2);
      if (statNodes.dg3) statNodes.dg3.textContent = String(dg3);
    }

    if (tableId === 'people-dashboard-table') {
      const statNodes = {
        total: document.querySelector('[data-people-stat="total"]'),
        dg1: document.querySelector('[data-people-stat="dg1"]'),
        dg2: document.querySelector('[data-people-stat="dg2"]'),
        dg3: document.querySelector('[data-people-stat="dg3"]')
      };
      const visibleRows = Array.from(rows).filter((row) => row.style.display !== 'none');
      let total = 0;
      let dg1 = 0;
      let dg2 = 0;
      let dg3 = 0;

      visibleRows.forEach((row) => {
        total += 1;
        const rowProgress = String(row.getAttribute('data-people-progress') || '').toLowerCase();
        if (rowProgress === 'dg1') {
          dg1 += 1;
        } else if (rowProgress === 'dg2') {
          dg2 += 1;
        } else if (rowProgress === 'dg3') {
          dg3 += 1;
        }
      });

      if (statNodes.total) statNodes.total.textContent = String(total);
      if (statNodes.dg1) statNodes.dg1.textContent = String(dg1);
      if (statNodes.dg2) statNodes.dg2.textContent = String(dg2);
      if (statNodes.dg3) statNodes.dg3.textContent = String(dg3);
    }
  }

  const bootLegacyApp = function () {
    const setupLiveJakartaTime = () => {
      const clockNodes = document.querySelectorAll('[data-live-jakarta-time]');
      if (clockNodes.length === 0) {
        return;
      }

      const formatter = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      });

      const syncClock = () => {
        const label = formatter.format(new Date()) + ' WIB';
        clockNodes.forEach((node) => {
          node.textContent = label;
        });
      };

      syncClock();
      window.setInterval(syncClock, 1000);
    };

    setupLiveJakartaTime();

    const enhanceTableCards = () => {
      const tableWraps = document.querySelectorAll('main.container .table-wrap');
      tableWraps.forEach((wrap) => {
        if (wrap.closest('.modal') || wrap.closest('.modal-card')) {
          return;
        }
        const card = wrap.closest('.card');
        if (!card) {
          return;
        }
        card.classList.add('table-card-plain');
      });
    };

    enhanceTableCards();

    const setupSuccessAlerts = (scope = document) => {
      const alerts = scope && scope.matches && scope.matches('.alert.success')
        ? [scope]
        : Array.from(scope.querySelectorAll('.alert.success'));
      alerts.forEach((alertEl, index) => {
        if (alertEl.getAttribute('data-success-alert-ready') === '1') {
          return;
        }
        alertEl.setAttribute('data-success-alert-ready', '1');
        const dismissAlert = () => {
          if (alertEl.dataset.dismissed === '1') {
            return;
          }
          alertEl.dataset.dismissed = '1';
          alertEl.classList.add('is-closing');
          window.setTimeout(() => {
            if (alertEl.parentNode) {
              alertEl.parentNode.removeChild(alertEl);
            }
          }, 220);
        };

        window.setTimeout(dismissAlert, 3000 + (index * 120));
      });
    };

    setupSuccessAlerts();

    const viewportFillTableConfigs = [
      {
        bodyClass: 'page-discipleship-groups-list',
        selector: '.discipleship-list-card .table-wrap',
        minHeight: 320,
        bottomOffset: 12,
        rowSnap: true
      },
      {
        bodyClass: 'page-discipleship-people-list',
        selector: '.discipleship-list-card .table-wrap',
        minHeight: 320,
        bottomOffset: 12,
        rowSnap: true
      }
    ];

    const clearViewportTableHeight = (wrap) => {
      wrap.style.removeProperty('height');
      wrap.style.removeProperty('max-height');
    };

    const syncViewportTableHeights = () => {
      const isNarrow = window.matchMedia('(max-width: 1024px)').matches;
      const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

      viewportFillTableConfigs.forEach((config) => {
        const wraps = document.querySelectorAll(config.selector);

        wraps.forEach((wrap) => {
          const tabPanel = wrap.closest('[data-discipleship-tab-panel]');
          const workspacePanels = wrap.closest('[data-discipleship-panels]');
          const isActivePage = tabPanel
            ? !tabPanel.hidden
            : document.body.classList.contains(config.bodyClass);
          const isWorkspacePanel = Boolean(tabPanel && workspacePanels);
          if (isWorkspacePanel) {
            clearViewportTableHeight(wrap);
            return;
          }
          const skipForNarrowViewport = isNarrow && !isWorkspacePanel;
          if (!isActivePage || skipForNarrowViewport) {
            clearViewportTableHeight(wrap);
            return;
          }

          const rect = wrap.getBoundingClientRect();
          const container = wrap.closest('.container');
          let maxBottom = viewportHeight - (config.bottomOffset || 0);
          if (container) {
            const containerRect = container.getBoundingClientRect();
            maxBottom = Math.min(maxBottom, containerRect.bottom);
          }

          const targetHeight = Math.floor(maxBottom - rect.top);
          let finalHeight = targetHeight;

          if (config.rowSnap) {
            const table = wrap.querySelector('table');
            const headRow = table ? table.querySelector('thead tr') : null;
            const firstVisibleRow = table
              ? table.querySelector('tbody tr:not([style*=\"display: none\"])')
              : null;

            if (headRow && firstVisibleRow) {
              const headHeight = Math.ceil(headRow.getBoundingClientRect().height || 0);
              const rowHeight = Math.ceil(firstVisibleRow.getBoundingClientRect().height || 0);
              if (headHeight > 0 && rowHeight > 0 && finalHeight > (headHeight + rowHeight)) {
                const rowsVisible = Math.max(1, Math.floor((finalHeight - headHeight - 2) / rowHeight));
                finalHeight = headHeight + (rowsVisible * rowHeight) + 2;
              }
            }
          }

          if (finalHeight >= (config.minHeight || 280)) {
            wrap.style.height = finalHeight + 'px';
            wrap.style.maxHeight = finalHeight + 'px';
          } else {
            clearViewportTableHeight(wrap);
          }
        });
      });
    };

    const syncBodyModalState = () => {
      document.body.classList.toggle('modal-open', Boolean(document.querySelector('.modal.is-open')));
    };

    const scheduleViewportTableHeights = () => {
      window.requestAnimationFrame(syncViewportTableHeights);
    };

    scheduleViewportTableHeights();
    window.setTimeout(scheduleViewportTableHeights, 120);
    window.addEventListener('resize', scheduleViewportTableHeights);

    const setupFilterControls = (scope = document) => {
      const root = scope && scope.querySelectorAll ? scope : document;
      const searchInputs = root.querySelectorAll('[data-filter]');
      searchInputs.forEach(input => {
        if (input.getAttribute('data-filter-ready') === '1') {
          return;
        }
        input.setAttribute('data-filter-ready', '1');
        input.addEventListener('input', function () {
          filterTable(input);
        });
        input.addEventListener('change', function () {
          filterTable(input);
        });
      });
    };

    setupFilterControls(document);

    const archiveWrap = document.querySelector('.member-left-archive-wrap');
    if (archiveWrap) {
      const archiveDetails = archiveWrap.closest('details');

      const resizeArchiveTable = () => {
        const table = archiveWrap.querySelector('table');
        if (!table) {
          return;
        }
        if (archiveDetails && !archiveDetails.open) {
          return;
        }

        const headRow = table.querySelector('thead tr');
        const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
        if (bodyRows.length === 0) {
          archiveWrap.style.removeProperty('height');
          archiveWrap.style.removeProperty('max-height');
          return;
        }

        const topRows = bodyRows.slice(0, 3);
        let targetHeight = 0;
        if (headRow) {
          targetHeight += headRow.getBoundingClientRect().height;
        }
        topRows.forEach((row) => {
          targetHeight += row.getBoundingClientRect().height;
        });

        targetHeight = Math.ceil(targetHeight + 2);
        archiveWrap.style.height = targetHeight + 'px';
        archiveWrap.style.maxHeight = targetHeight + 'px';
      };

      const scheduleArchiveResize = () => {
        window.requestAnimationFrame(resizeArchiveTable);
      };

      scheduleArchiveResize();
      window.addEventListener('resize', scheduleArchiveResize);
      if (archiveDetails) {
        archiveDetails.addEventListener('toggle', scheduleArchiveResize);
      }
    }

    const setupAutoSubmitSearchForms = (scope = document) => {
      const root = scope && scope.querySelectorAll ? scope : document;
      const autoSubmitSearchForms = root.querySelectorAll('[data-auto-submit-search-form]');
      autoSubmitSearchForms.forEach((form) => {
        if (form.getAttribute('data-auto-submit-search-ready') === '1') {
          return;
        }
        const input = form.querySelector('[data-auto-submit-search-input]');
        if (!input) {
          return;
        }
        form.setAttribute('data-auto-submit-search-ready', '1');
        let submitTimer = null;
        const submitNow = () => {
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        };
        input.addEventListener('input', function () {
          if (submitTimer !== null) {
            window.clearTimeout(submitTimer);
          }
          submitTimer = window.setTimeout(function () {
            submitNow();
          }, 300);
        });
        form.addEventListener('submit', function () {
          if (submitTimer !== null) {
            window.clearTimeout(submitTimer);
            submitTimer = null;
          }
        });
      });
    };

    setupAutoSubmitSearchForms(document);

    const cssAttributeValue = (value) => {
      const text = String(value || '');
      if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(text);
      }

      return text.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    };

    const queryTemplateByAttribute = (attribute, value) => {
      const key = String(value || '').trim();
      if (!key) {
        return null;
      }

      return document.querySelector('template[' + attribute + '="' + cssAttributeValue(key) + '"]');
    };

    const initLiveTableSearch = ({ formSelector, inputSelector, rowSelector, emptySelector }) => {
      const form = document.querySelector(formSelector);
      if (!form) {
        return;
      }
      const input = form.querySelector(inputSelector);
      const rows = Array.from(document.querySelectorAll(rowSelector));
      const emptyRow = document.querySelector(emptySelector);
      if (!input) {
        return;
      }

      const normalizeSearch = (value) => String(value || '')
        .trim()
        .toLocaleLowerCase('id-ID');

      const filterRows = () => {
        const rawQuery = String(input.value || '').trim();
        const query = normalizeSearch(rawQuery);
        let visibleRows = 0;

        rows.forEach((row) => {
          const searchText = normalizeSearch(row.getAttribute('data-search-text'));
          const matches = query === '' || searchText.includes(query);
          row.hidden = !matches;
          if (matches) {
            visibleRows += 1;
          }
        });

        if (emptyRow) {
          emptyRow.hidden = visibleRows !== 0;
        }
        if (window.history && typeof window.history.replaceState === 'function') {
          const url = new URL(window.location.href);
          if (rawQuery) {
            url.searchParams.set('q', rawQuery);
          } else {
            url.searchParams.delete('q');
          }
          url.searchParams.delete('page');
          window.history.replaceState(window.history.state, '', url.toString());
        }
      };

      input.addEventListener('input', filterRows);
      form.addEventListener('submit', (event) => {
        if (event.submitter && event.submitter.hasAttribute('data-live-search-external-submit')) {
          return;
        }
        event.preventDefault();
        filterRows();
      });
      filterRows();
    };

    const setupDiscipleshipPeopleList = (scope = document) => {
      const root = scope && scope.matches && scope.matches('[data-discipleship-people-list]')
        ? scope
        : scope.querySelector('[data-discipleship-people-list]');
      const panel = root ? (root.closest('[data-discipleship-tab-panel]') || root.parentElement || document) : null;
      const form = panel ? panel.querySelector('[data-discipleship-people-search-form]') : null;
      if (!root || !form) {
        return;
      }
      if (root.getAttribute('data-discipleship-people-list-ready') === '1') {
        return;
      }
      root.setAttribute('data-discipleship-people-list-ready', '1');

      const input = form.querySelector('[data-discipleship-people-search-input]');
      const progressInput = form.querySelector('[data-discipleship-people-progress-input]');
      const tableScrollArea = root.querySelector('[data-discipleship-people-scroll]');
      const scrollArea = panel && panel.closest('[data-discipleship-workspace]')
        ? null
        : tableScrollArea;
      const body = root.querySelector('[data-discipleship-people-list-body]');
      const emptyRow = root.querySelector('[data-discipleship-people-search-empty]');
      const loadingRow = root.querySelector('[data-discipleship-people-loading]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        total: panel.querySelector('[data-people-stat="total"]'),
        dg1: panel.querySelector('[data-people-stat="dg1"]'),
        dg2: panel.querySelector('[data-people-stat="dg2"]'),
        dg3: panel.querySelector('[data-people-stat="dg3"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextCursor = root.getAttribute('data-next-cursor') || null;
      const limit = Math.min(100, Math.max(1, parseInt(root.getAttribute('data-limit') || '50', 10) || 50));
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;
      let activeRequest = null;
      let pendingRequest = null;
      let loadMoreObserver = null;
      let destroyed = false;

      const currentQuery = () => String(input.value || '').trim();
      const currentProgress = () => String(progressInput ? progressInput.value : 'all').trim() || 'all';
      const isPanelHidden = () => destroyed || document.hidden || Boolean(
        panel && (panel.hidden || panel.getAttribute('aria-hidden') === 'true')
      );

      const updateStats = (stats) => {
        if (!stats || typeof stats !== 'object') {
          return;
        }
        Object.keys(statNodes).forEach((key) => {
          if (statNodes[key] && Object.prototype.hasOwnProperty.call(stats, key)) {
            statNodes[key].textContent = String(stats[key] ?? 0);
          }
        });
      };

      const setLoading = (value) => {
        if (loadingRow) {
          loadingRow.hidden = !value;
        }
      };

      const visibleRowCount = () => body.querySelectorAll('[data-discipleship-people-search-row]').length;

      const setEmptyState = (message) => {
        if (!emptyRow) {
          return;
        }
        const cell = emptyRow.querySelector('td');
        if (cell && message) {
          cell.textContent = message;
        }
        emptyRow.hidden = visibleRowCount() !== 0;
      };

      const clearRows = () => {
        body.querySelectorAll('[data-discipleship-people-search-row]').forEach((row) => {
          row.remove();
        });
      };

      const insertRows = (html) => {
        const anchor = loadingRow || emptyRow;
        const content = String(html || '').trim();
        if (content === '') {
          return;
        }
        if (anchor) {
          anchor.insertAdjacentHTML('beforebegin', content);
        } else {
          body.insertAdjacentHTML('beforeend', content);
        }
      };

      const syncUrl = () => {
        if (!window.history || typeof window.history.replaceState !== 'function') {
          return;
        }
        const url = new URL(window.location.href);
        const query = currentQuery();
        const progress = currentProgress();
        if (query) {
          url.searchParams.set('q', query);
        } else {
          url.searchParams.delete('q');
        }
        if (progress !== 'all') {
          url.searchParams.set('progress', progress);
        } else {
          url.searchParams.delete('progress');
        }
        url.searchParams.delete('page');
        url.searchParams.delete('per_page');
        url.searchParams.delete('cursor');
        url.searchParams.delete('limit');
        window.history.replaceState(window.history.state, '', url.toString());
        window.dispatchEvent(new CustomEvent('discipleship:panel-url-change', {
          detail: { tabKey: 'people', url: url.toString() }
        }));
      };

      const buildRowsUrl = (cursor) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('progress', currentProgress());
        if (cursor) {
          url.searchParams.set('cursor', cursor);
        }
        url.searchParams.set('limit', String(limit));
        url.searchParams.set('stats', '1');
        if (branchInput && String(branchInput.value || '').trim() !== '') {
          url.searchParams.set('branch_id', String(branchInput.value).trim());
        }

        return url;
      };

      const applyResult = (data, mode) => {
        if (mode === 'replace') {
          clearRows();
        }
        insertRows(data && data.html);
        hasMore = Boolean(data && data.has_more);
        nextCursor = hasMore ? (String(data && data.next_cursor || '').trim() || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-cursor', nextCursor || '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Peserta tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadCursor = (cursor, mode) => {
        if (isPanelHidden()) {
          pendingRequest = { cursor, mode, syncUrl: false };
          return;
        }
        if (mode === 'append' && (!hasMore || !cursor || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const controller = activeController;
        const requestId = ++requestSeq;
        activeRequest = { cursor, mode };
        pendingRequest = null;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(cursor).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error('Gagal memuat daftar anggota DG.');
            }

            return response.json();
          })
          .then((data) => {
            if (requestId !== requestSeq) {
              return;
            }
            applyResult(data, mode);
            window.requestAnimationFrame(loadMoreIfNeeded);
          })
          .catch((error) => {
            if (error && error.name === 'AbortError') {
              return;
            }
            if (mode === 'replace') {
              clearRows();
              setEmptyState('Data anggota DG gagal dimuat.');
            }
          })
          .finally(() => {
            if (requestId !== requestSeq) {
              return;
            }
            isLoading = false;
            activeRequest = null;
            if (activeController === controller) {
              activeController = null;
            }
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (isPanelHidden()) {
          return;
        }
        if (hasMore && nextCursor && !isLoading && nearBottom()) {
          loadCursor(nextCursor, 'append');
        }
      };

      input.addEventListener('input', scheduleSearch);
      if (progressInput) {
        progressInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        });
      }
      form.addEventListener('submit', (event) => {
        if (event.submitter && event.submitter.hasAttribute('data-live-search-external-submit')) {
          return;
        }
        event.preventDefault();
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
        }
        if (isPanelHidden()) {
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
          return;
        }
        syncUrl();
        loadCursor(null, 'replace');
      });
      const sentinel = document.createElement('div');
      sentinel.className = 'lazy-list-sentinel';
      sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'height:1px;width:100%;pointer-events:none';
      (tableScrollArea || root).appendChild(sentinel);
      if ('IntersectionObserver' in window) {
        loadMoreObserver = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            loadMoreIfNeeded();
          }
        }, {
          root: scrollArea instanceof HTMLElement ? scrollArea : null,
          rootMargin: '180px 0px'
        });
        loadMoreObserver.observe(sentinel);
      } else {
        if (scrollArea) {
          scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        }
        window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        window.addEventListener('resize', loadMoreIfNeeded);
      }
      const suspendPanel = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
        }
        if (activeController) {
          if (!pendingRequest && activeRequest) {
            pendingRequest = { ...activeRequest, syncUrl: false };
          }
          requestSeq += 1;
          activeController.abort();
          activeController = null;
          activeRequest = null;
          isLoading = false;
          setLoading(false);
        }
      };
      panel.addEventListener('discipleship:panel-deactivate', suspendPanel);
      panel.addEventListener('discipleship:panel-destroy', () => {
        suspendPanel();
        destroyed = true;
        pendingRequest = null;
        if (loadMoreObserver) {
          loadMoreObserver.disconnect();
          loadMoreObserver = null;
        }
        if (scrollArea) {
          scrollArea.removeEventListener('scroll', loadMoreIfNeeded);
        }
        window.removeEventListener('scroll', loadMoreIfNeeded);
        window.removeEventListener('resize', loadMoreIfNeeded);
      });
      panel.addEventListener('discipleship:panel-activate', () => {
        if (destroyed) {
          return;
        }
        if (pendingRequest) {
          const request = pendingRequest;
          pendingRequest = null;
          if (request.syncUrl) {
            syncUrl();
          }
          loadCursor(request.cursor, request.mode);
          return;
        }
        window.requestAnimationFrame(loadMoreIfNeeded);
      });
      setEmptyState('Peserta tidak ditemukan.');
    };

    const setupDiscipleshipGroupsList = (scope = document) => {
      const root = scope && scope.matches && scope.matches('[data-discipleship-groups-list]')
        ? scope
        : scope.querySelector('[data-discipleship-groups-list]');
      const panel = root ? (root.closest('[data-discipleship-tab-panel]') || root.parentElement || document) : null;
      const form = panel ? panel.querySelector('[data-discipleship-groups-search-form]') : null;
      if (!root || !form) {
        return;
      }
      if (root.getAttribute('data-discipleship-groups-list-ready') === '1') {
        return;
      }
      root.setAttribute('data-discipleship-groups-list-ready', '1');

      const input = form.querySelector('[data-discipleship-groups-search-input]');
      const statusInput = form.querySelector('[data-discipleship-groups-status-input]');
      const tableScrollArea = root.querySelector('[data-discipleship-groups-scroll]');
      const scrollArea = panel && panel.closest('[data-discipleship-workspace]')
        ? null
        : tableScrollArea;
      const body = root.querySelector('[data-discipleship-groups-list-body]');
      const emptyRow = root.querySelector('[data-discipleship-groups-empty]');
      const loadingRow = root.querySelector('[data-discipleship-groups-loading]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        total: panel.querySelector('[data-groups-stat="total"]'),
        dg1: panel.querySelector('[data-groups-stat="dg1"]'),
        dg2: panel.querySelector('[data-groups-stat="dg2"]'),
        dg3: panel.querySelector('[data-groups-stat="dg3"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextCursor = root.getAttribute('data-next-cursor') || null;
      const limit = Math.min(100, Math.max(1, parseInt(root.getAttribute('data-limit') || '50', 10) || 50));
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;
      let activeRequest = null;
      let pendingRequest = null;
      let loadMoreObserver = null;
      let destroyed = false;

      const currentQuery = () => String(input.value || '').trim();
      const currentStatus = () => String(statusInput ? statusInput.value : 'all').trim() || 'all';
      const isPanelHidden = () => destroyed || document.hidden || Boolean(
        panel && (panel.hidden || panel.getAttribute('aria-hidden') === 'true')
      );

      const updateStats = (stats) => {
        if (!stats || typeof stats !== 'object') {
          return;
        }
        Object.keys(statNodes).forEach((key) => {
          if (statNodes[key] && Object.prototype.hasOwnProperty.call(stats, key)) {
            statNodes[key].textContent = String(stats[key] ?? 0);
          }
        });
      };

      const setLoading = (value) => {
        if (loadingRow) {
          loadingRow.hidden = !value;
        }
      };

      const visibleRowCount = () => body.querySelectorAll('[data-discipleship-groups-row]').length;

      const setEmptyState = (message) => {
        if (!emptyRow) {
          return;
        }
        const cell = emptyRow.querySelector('td');
        if (cell && message) {
          cell.textContent = message;
        }
        emptyRow.hidden = visibleRowCount() !== 0;
      };

      const clearRows = () => {
        body.querySelectorAll('[data-discipleship-groups-row]').forEach((row) => {
          row.remove();
        });
      };

      const insertRows = (html) => {
        const content = String(html || '').trim();
        if (content === '') {
          return;
        }
        const anchor = loadingRow || emptyRow;
        if (anchor) {
          anchor.insertAdjacentHTML('beforebegin', content);
        } else {
          body.insertAdjacentHTML('beforeend', content);
        }
      };

      const syncUrl = () => {
        if (!window.history || typeof window.history.replaceState !== 'function') {
          return;
        }
        const url = new URL(window.location.href);
        const query = currentQuery();
        const status = currentStatus();
        if (query) {
          url.searchParams.set('q', query);
        } else {
          url.searchParams.delete('q');
        }
        if (status !== 'all') {
          url.searchParams.set('status', status);
        } else {
          url.searchParams.delete('status');
        }
        url.searchParams.delete('page');
        url.searchParams.delete('per_page');
        url.searchParams.delete('cursor');
        url.searchParams.delete('limit');
        window.history.replaceState(window.history.state, '', url.toString());
        window.dispatchEvent(new CustomEvent('discipleship:panel-url-change', {
          detail: { tabKey: 'groups', url: url.toString() }
        }));
      };

      const buildRowsUrl = (cursor) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('status', currentStatus());
        if (cursor) {
          url.searchParams.set('cursor', cursor);
        }
        url.searchParams.set('limit', String(limit));
        if (branchInput && String(branchInput.value || '').trim() !== '') {
          url.searchParams.set('branch_id', String(branchInput.value).trim());
        }

        return url;
      };

      const applyResult = (data, mode) => {
        if (mode === 'replace') {
          clearRows();
        }
        insertRows(data && data.html);
        hasMore = Boolean(data && data.has_more);
        nextCursor = hasMore ? (String(data && data.next_cursor || '').trim() || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-cursor', nextCursor || '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Kelompok tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadCursor = (cursor, mode) => {
        if (isPanelHidden()) {
          pendingRequest = { cursor, mode, syncUrl: false };
          return;
        }
        if (mode === 'append' && (!hasMore || !cursor || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const controller = activeController;
        const requestId = ++requestSeq;
        activeRequest = { cursor, mode };
        pendingRequest = null;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(cursor).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error('Gagal memuat kelompok DG.');
            }

            return response.json();
          })
          .then((data) => {
            if (requestId !== requestSeq) {
              return;
            }
            applyResult(data, mode);
            window.requestAnimationFrame(loadMoreIfNeeded);
          })
          .catch((error) => {
            if (error && error.name === 'AbortError') {
              return;
            }
            if (mode === 'replace') {
              clearRows();
              setEmptyState('Data kelompok DG gagal dimuat.');
            }
          })
          .finally(() => {
            if (requestId !== requestSeq) {
              return;
            }
            isLoading = false;
            activeRequest = null;
            if (activeController === controller) {
              activeController = null;
            }
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (isPanelHidden()) {
          return;
        }
        if (hasMore && nextCursor && !isLoading && nearBottom()) {
          loadCursor(nextCursor, 'append');
        }
      };

      input.addEventListener('input', scheduleSearch);
      if (statusInput) {
        statusInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        });
      }
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
        }
        if (isPanelHidden()) {
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
          return;
        }
        syncUrl();
        loadCursor(null, 'replace');
      });
      const sentinel = document.createElement('div');
      sentinel.className = 'lazy-list-sentinel';
      sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'height:1px;width:100%;pointer-events:none';
      (tableScrollArea || root).appendChild(sentinel);
      if ('IntersectionObserver' in window) {
        loadMoreObserver = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            loadMoreIfNeeded();
          }
        }, {
          root: scrollArea instanceof HTMLElement ? scrollArea : null,
          rootMargin: '180px 0px'
        });
        loadMoreObserver.observe(sentinel);
      } else {
        if (scrollArea) {
          scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        }
        window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        window.addEventListener('resize', loadMoreIfNeeded);
      }
      const suspendPanel = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
        }
        if (activeController) {
          if (!pendingRequest && activeRequest) {
            pendingRequest = { ...activeRequest, syncUrl: false };
          }
          requestSeq += 1;
          activeController.abort();
          activeController = null;
          activeRequest = null;
          isLoading = false;
          setLoading(false);
        }
      };
      panel.addEventListener('discipleship:panel-deactivate', suspendPanel);
      panel.addEventListener('discipleship:panel-destroy', () => {
        suspendPanel();
        destroyed = true;
        pendingRequest = null;
        if (loadMoreObserver) {
          loadMoreObserver.disconnect();
          loadMoreObserver = null;
        }
        if (scrollArea) {
          scrollArea.removeEventListener('scroll', loadMoreIfNeeded);
        }
        window.removeEventListener('scroll', loadMoreIfNeeded);
        window.removeEventListener('resize', loadMoreIfNeeded);
      });
      panel.addEventListener('discipleship:panel-activate', () => {
        if (destroyed) {
          return;
        }
        if (pendingRequest) {
          const request = pendingRequest;
          pendingRequest = null;
          if (request.syncUrl) {
            syncUrl();
          }
          loadCursor(request.cursor, request.mode);
          return;
        }
        window.requestAnimationFrame(loadMoreIfNeeded);
      });
      setEmptyState('Kelompok tidak ditemukan.');
    };

    const setupDiscipleshipDashboard = (scope = document) => {
      const panel = scope && scope.matches && scope.matches('[data-discipleship-dashboard-panel]')
        ? scope
        : scope.querySelector('[data-discipleship-dashboard-panel]');
      if (!panel || panel.getAttribute('data-discipleship-dashboard-ready') === '1') {
        return;
      }
      panel.setAttribute('data-discipleship-dashboard-ready', '1');

      const sections = Array.from(panel.querySelectorAll('[data-dashboard-section]'));
      const modal = panel.querySelector('[data-msk-edit-modal]');
      const sectionStates = new Map();
      let destroyed = false;

      sections.forEach((section) => {
        sectionStates.set(section, {
          controller: null,
          loaded: section.getAttribute('data-dashboard-section-loaded') === '1',
          pending: section.getAttribute('data-dashboard-section-loaded') !== '1',
          sequence: 0
        });
      });

      const isPanelHidden = () => panel.hidden || panel.getAttribute('aria-hidden') === 'true';

      const closeMskEditor = () => {
        if (!modal) {
          return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
      };

      const templateForParticipant = (participantId) => {
        const key = String(participantId || '').trim();
        if (!key) {
          return null;
        }
        return Array.from(panel.querySelectorAll('template[data-msk-edit-template]')).find((template) => (
          String(template.getAttribute('data-msk-edit-template') || '') === key
        )) || null;
      };

      const openMskEditor = (template) => {
        if (!modal || !template || isPanelHidden()) {
          return;
        }
        const titleEl = modal.querySelector('[data-msk-edit-title]');
        const bodyEl = modal.querySelector('[data-msk-edit-body]');
        if (titleEl) {
          titleEl.textContent = template.getAttribute('data-msk-edit-template-title') || 'Edit Sesi MSK';
        }
        if (bodyEl) {
          bodyEl.innerHTML = template.innerHTML;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const firstInput = bodyEl ? bodyEl.querySelector('input, select, textarea') : null;
        if (firstInput && typeof firstInput.focus === 'function') {
          firstInput.focus();
        }
      };

      const openRequestedEditor = (section) => {
        const participantId = String(section.getAttribute('data-auto-edit-id') || '').trim();
        if (!participantId) {
          return;
        }
        const template = templateForParticipant(participantId);
        if (template) {
          section.removeAttribute('data-auto-edit-id');
          openMskEditor(template);
        }
      };

      const showSectionError = (section) => {
        section.removeAttribute('aria-busy');
        section.innerHTML = '<div class="dashboard-section-error"><strong>Data belum dapat dimuat.</strong><button class="btn tiny secondary" type="button" data-dashboard-section-retry>Coba lagi</button></div>';
      };

      const loadSection = async (section) => {
        const state = sectionStates.get(section);
        const url = String(section.getAttribute('data-section-url') || '').trim();
        if (!state || !url || destroyed) {
          return;
        }
        if (isPanelHidden()) {
          state.pending = !state.loaded;
          return;
        }
        if (state.loaded || state.controller) {
          return;
        }

        const controller = new AbortController();
        const sequence = ++state.sequence;
        state.controller = controller;
        state.pending = false;
        section.setAttribute('aria-busy', 'true');

        try {
          const response = await window.fetch(url, {
            credentials: 'same-origin',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'text/html'
            },
            signal: controller.signal
          });
          if (controller.signal.aborted || sequence !== state.sequence || destroyed) {
            return;
          }
          if (response.redirected) {
            window.location.assign(response.url || url);
            return;
          }
          const contentType = String(response.headers.get('content-type') || '').toLowerCase();
          if (!response.ok || !contentType.includes('text/html')) {
            throw new Error('dashboard-section');
          }
          const html = await response.text();
          if (controller.signal.aborted || sequence !== state.sequence || destroyed) {
            return;
          }
          section.innerHTML = html;
          section.removeAttribute('aria-busy');
          section.setAttribute('data-dashboard-section-loaded', '1');
          state.loaded = true;
          state.pending = false;
          openRequestedEditor(section);
        } catch (error) {
          if (controller.signal.aborted || sequence !== state.sequence || destroyed) {
            return;
          }
          state.pending = false;
          showSectionError(section);
        } finally {
          if (state.controller === controller) {
            state.controller = null;
          }
        }
      };

      const activatePanel = () => {
        if (destroyed || isPanelHidden()) {
          return;
        }
        sections.forEach((section) => {
          const state = sectionStates.get(section);
          if (state && !state.loaded && state.pending) {
            loadSection(section);
          }
        });
      };

      const suspendPanel = () => {
        sectionStates.forEach((state, section) => {
          if (state.controller) {
            state.sequence += 1;
            state.controller.abort();
            state.controller = null;
            section.removeAttribute('aria-busy');
            state.pending = !state.loaded;
          }
        });
        closeMskEditor();
      };

      panel.addEventListener('click', (event) => {
        const retry = event.target.closest('[data-dashboard-section-retry]');
        if (retry) {
          const section = retry.closest('[data-dashboard-section]');
          const state = section ? sectionStates.get(section) : null;
          if (section && state) {
            state.pending = true;
            loadSection(section);
          }
          return;
        }

        const edit = event.target.closest('[data-msk-edit-open]');
        if (edit) {
          const template = templateForParticipant(edit.getAttribute('data-msk-edit-open'));
          if (template) {
            event.preventDefault();
            openMskEditor(template);
          }
          return;
        }

        if (modal && (event.target === modal || event.target.closest('[data-msk-edit-close]'))) {
          event.preventDefault();
          closeMskEditor();
        }
      });
      panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
          closeMskEditor();
        }
      });
      panel.addEventListener('discipleship:panel-activate', activatePanel);
      panel.addEventListener('discipleship:panel-deactivate', suspendPanel);
      panel.addEventListener('discipleship:panel-destroy', () => {
        suspendPanel();
        destroyed = true;
        sectionStates.clear();
      });

      setupSuccessAlerts(panel);
      activatePanel();
    };

    const setupMskList = (scope = document) => {
      const root = scope && scope.matches && scope.matches('[data-msk-list]')
        ? scope
        : (scope && scope.querySelector ? scope.querySelector('[data-msk-list]') : null);
      const panel = root ? (root.closest('[data-discipleship-tab-panel]') || root.parentElement || document) : null;
      const form = panel ? panel.querySelector('[data-msk-search-form]') : null;
      if (!root || !form) {
        return;
      }
      if (root.getAttribute('data-msk-list-ready') === '1') {
        return;
      }
      root.setAttribute('data-msk-list-ready', '1');

      const input = form.querySelector('[data-msk-search-input]');
      const batchInput = panel.querySelector('[data-msk-batch-input]');
      const tableScrollArea = root.querySelector('[data-msk-scroll]');
      const scrollArea = panel && panel.closest('[data-discipleship-workspace]')
        ? null
        : tableScrollArea;
      const body = root.querySelector('[data-msk-list-body]');
      const emptyRow = root.querySelector('[data-msk-search-empty]');
      const loadingRow = root.querySelector('[data-msk-loading]');
      const templatesContainer = panel.querySelector('[data-msk-view-templates]');
      const editTemplatesContainer = panel.querySelector('[data-msk-edit-templates]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        filter: panel.querySelector('[data-msk-stat="filter"]'),
        total: panel.querySelector('[data-msk-stat="total"]'),
        complete: panel.querySelector('[data-msk-stat="complete"]'),
        progress: panel.querySelector('[data-msk-stat="progress"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextCursor = root.getAttribute('data-next-cursor') || null;
      const limit = Math.min(100, Math.max(1, parseInt(root.getAttribute('data-limit') || '50', 10) || 50));
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;
      let activeRequest = null;
      let pendingRequest = null;
      let loadMoreObserver = null;
      let destroyed = false;

      const isPanelHidden = () => Boolean(
        document.hidden
        || (panel instanceof HTMLElement && (panel.hidden || panel.getAttribute('aria-hidden') === 'true'))
      );

      const currentQuery = () => String(input.value || '').trim();
      const currentBatch = () => {
        if (batchInput) {
          return String(batchInput.value || '').trim() || 'all';
        }
        const hiddenBatch = form.querySelector('input[name="batch_month"]');

        return hiddenBatch ? (String(hiddenBatch.value || '').trim() || 'all') : 'all';
      };

      const syncBatchHiddenInputs = () => {
        const batch = currentBatch();
        panel.querySelectorAll('input[name="batch_month"]').forEach((node) => {
          node.value = batch;
        });
      };

      const syncSearchHiddenInputs = () => {
        const query = currentQuery();
        panel.querySelectorAll('input[type="hidden"][name="q"]').forEach((node) => {
          node.value = query;
        });
      };

      const updateStats = (stats) => {
        if (!stats || typeof stats !== 'object') {
          return;
        }
        Object.keys(statNodes).forEach((key) => {
          if (statNodes[key] && Object.prototype.hasOwnProperty.call(stats, key)) {
            statNodes[key].textContent = String(stats[key] ?? 0);
          }
        });
      };

      const setLoading = (value) => {
        if (loadingRow) {
          loadingRow.hidden = !value;
        }
      };

      const visibleRowCount = () => body.querySelectorAll('[data-msk-search-row]').length;

      const setEmptyState = (message) => {
        if (!emptyRow) {
          return;
        }
        const cell = emptyRow.querySelector('td');
        if (cell && message) {
          cell.textContent = message;
        }
        emptyRow.hidden = visibleRowCount() !== 0;
      };

      const clearStaleSelectionAlerts = () => {
        panel.querySelectorAll('.alert.danger').forEach((alertEl) => {
          const message = String(alertEl.textContent || '').trim();
          if (
            message === 'Data peserta kelas MSK yang ingin diedit tidak ditemukan.' ||
            message === 'Data peserta kelas MSK yang ingin dilihat tidak ditemukan.'
          ) {
            alertEl.remove();
          }
        });
      };

      const clearRows = () => {
        body.querySelectorAll('[data-msk-search-row]').forEach((row) => {
          row.remove();
        });
      };

      const insertRows = (html) => {
        const content = String(html || '').trim();
        if (content === '') {
          return;
        }
        const anchor = loadingRow || emptyRow;
        if (anchor) {
          anchor.insertAdjacentHTML('beforebegin', content);
        } else {
          body.insertAdjacentHTML('beforeend', content);
        }
      };

      const insertTemplates = (html, mode) => {
        if (!templatesContainer) {
          return;
        }
        if (mode === 'replace') {
          templatesContainer.innerHTML = '';
        }
        const content = String(html || '').trim();
        if (content !== '') {
          templatesContainer.insertAdjacentHTML('beforeend', content);
        }
      };

      const insertEditTemplates = (html, mode) => {
        if (!editTemplatesContainer) {
          return;
        }
        if (mode === 'replace') {
          editTemplatesContainer.innerHTML = '';
        }
        const content = String(html || '').trim();
        if (content !== '') {
          editTemplatesContainer.insertAdjacentHTML('beforeend', content);
        }
      };

      const syncUrl = () => {
        if (!window.history || typeof window.history.replaceState !== 'function') {
          return;
        }
        const url = new URL(window.location.href);
        const query = currentQuery();
        const batch = currentBatch();
        if (query) {
          url.searchParams.set('q', query);
        } else {
          url.searchParams.delete('q');
        }
        if (batch) {
          url.searchParams.set('batch_month', batch);
        } else {
          url.searchParams.delete('batch_month');
        }
        url.searchParams.delete('page');
        url.searchParams.delete('per_page');
        url.searchParams.delete('cursor');
        url.searchParams.delete('limit');
        url.searchParams.delete('edit');
        url.searchParams.delete('view');
        window.history.replaceState(window.history.state, '', url.toString());
        window.dispatchEvent(new CustomEvent('discipleship:panel-url-change', {
          detail: { tabKey: 'msk', url: url.toString() }
        }));
      };

      const buildRowsUrl = (cursor) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]') || panel.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('batch_month', currentBatch());
        if (cursor) {
          url.searchParams.set('cursor', cursor);
        }
        url.searchParams.set('limit', String(limit));
        if (branchInput && String(branchInput.value || '').trim() !== '') {
          url.searchParams.set('branch_id', String(branchInput.value).trim());
        }

        return url;
      };

      const applyResult = (data, mode) => {
        if (mode === 'replace') {
          clearStaleSelectionAlerts();
          clearRows();
        }
        insertRows(data && data.html);
        insertTemplates(data && data.templates_html, mode);
        insertEditTemplates(data && data.edit_templates_html, mode);
        hasMore = Boolean(data && data.has_more);
        nextCursor = hasMore ? (String(data && data.next_cursor || '').trim() || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-cursor', nextCursor || '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Peserta tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadCursor = (cursor, mode) => {
        if (isPanelHidden()) {
          pendingRequest = { cursor, mode, syncUrl: false };
          return;
        }
        if (mode === 'append' && (!hasMore || !cursor || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const controller = activeController;
        const requestId = ++requestSeq;
        activeRequest = { cursor, mode };
        pendingRequest = null;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(cursor).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error('Gagal memuat peserta MSK.');
            }

            return response.json();
          })
          .then((data) => {
            if (requestId !== requestSeq) {
              return;
            }
            applyResult(data, mode);
            window.requestAnimationFrame(loadMoreIfNeeded);
          })
          .catch((error) => {
            if (error && error.name === 'AbortError') {
              return;
            }
            if (mode === 'replace') {
              clearRows();
              setEmptyState('Data peserta MSK gagal dimuat.');
            }
          })
          .finally(() => {
            if (requestId !== requestSeq) {
              return;
            }
            isLoading = false;
            activeRequest = null;
            if (activeController === controller) {
              activeController = null;
            }
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          syncBatchHiddenInputs();
          syncSearchHiddenInputs();
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (!isPanelHidden() && hasMore && nextCursor && !isLoading && nearBottom()) {
          loadCursor(nextCursor, 'append');
        }
      };

      input.addEventListener('input', () => {
        syncSearchHiddenInputs();
        scheduleSearch();
      });
      if (batchInput) {
        batchInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          syncBatchHiddenInputs();
          syncSearchHiddenInputs();
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        });
      }
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
        }
        syncBatchHiddenInputs();
        syncSearchHiddenInputs();
        if (isPanelHidden()) {
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
          return;
        }
        syncUrl();
        loadCursor(null, 'replace');
      });

      const sentinel = document.createElement('div');
      sentinel.className = 'lazy-list-sentinel';
      sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'height:1px;width:100%;pointer-events:none';
      (tableScrollArea || root).appendChild(sentinel);
      if ('IntersectionObserver' in window) {
        loadMoreObserver = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            loadMoreIfNeeded();
          }
        }, {
          root: scrollArea instanceof HTMLElement ? scrollArea : null,
          rootMargin: '180px 0px'
        });
        loadMoreObserver.observe(sentinel);
      } else {
        if (scrollArea) {
          scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        }
        window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        window.addEventListener('resize', loadMoreIfNeeded);
      }

      const suspendPanel = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
        }
        if (activeController) {
          if (!pendingRequest && activeRequest) {
            pendingRequest = { ...activeRequest, syncUrl: false };
          }
          requestSeq += 1;
          activeController.abort();
          activeController = null;
          activeRequest = null;
          isLoading = false;
          setLoading(false);
        }
      };
      const resumePanel = () => {
        if (destroyed || isPanelHidden()) {
          return;
        }
        if (pendingRequest) {
          const request = pendingRequest;
          pendingRequest = null;
          if (request.syncUrl) {
            syncUrl();
          }
          loadCursor(request.cursor, request.mode);
          return;
        }
        window.requestAnimationFrame(loadMoreIfNeeded);
      };
      const handleVisibilityChange = () => {
        if (document.hidden) {
          suspendPanel();
        } else {
          resumePanel();
        }
      };
      panel.addEventListener('discipleship:panel-deactivate', suspendPanel);
      panel.addEventListener('discipleship:panel-activate', resumePanel);
      document.addEventListener('visibilitychange', handleVisibilityChange);
      panel.addEventListener('discipleship:panel-destroy', () => {
        suspendPanel();
        destroyed = true;
        pendingRequest = null;
        if (loadMoreObserver) {
          loadMoreObserver.disconnect();
          loadMoreObserver = null;
        } else {
          if (scrollArea) {
            scrollArea.removeEventListener('scroll', loadMoreIfNeeded);
          }
          window.removeEventListener('scroll', loadMoreIfNeeded);
          window.removeEventListener('resize', loadMoreIfNeeded);
        }
        document.removeEventListener('visibilitychange', handleVisibilityChange);
      });
      setEmptyState('Peserta tidak ditemukan.');
    };


    const setupSpiritualJourneyList = (scope = document) => {
      const root = scope && scope.matches && scope.matches('[data-spiritual-journey-list]')
        ? scope
        : (scope && scope.querySelector ? scope.querySelector('[data-spiritual-journey-list]') : null);
      const panel = root ? (root.closest('[data-discipleship-tab-panel]') || root.parentElement || document) : null;
      const form = panel ? panel.querySelector('[data-spiritual-journey-search-form]') : null;
      if (!root || !form) {
        return;
      }
      if (root.getAttribute('data-spiritual-journey-list-ready') === '1') {
        return;
      }
      root.setAttribute('data-spiritual-journey-list-ready', '1');

      const input = form.querySelector('[data-spiritual-journey-search-input]');
      const filterInput = form.querySelector('[data-spiritual-journey-filter-input]');
      const tableScrollArea = root.querySelector('[data-spiritual-journey-scroll]');
      const scrollArea = panel && panel.closest('[data-discipleship-workspace]')
        ? null
        : tableScrollArea;
      const body = root.querySelector('[data-spiritual-journey-list-body]');
      const emptyRow = root.querySelector('[data-spiritual-journey-search-empty]');
      const loadingRow = root.querySelector('[data-spiritual-journey-loading]');
      const templatesContainer = panel.querySelector('[data-spiritual-journey-view-templates]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        dg1: panel.querySelector('[data-spiritual-journey-stat="dg1"]'),
        kgap: panel.querySelector('[data-spiritual-journey-stat="kgap"]'),
        dg2: panel.querySelector('[data-spiritual-journey-stat="dg2"]'),
        dg3: panel.querySelector('[data-spiritual-journey-stat="dg3"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextCursor = root.getAttribute('data-next-cursor') || null;
      const limit = Math.min(100, Math.max(1, parseInt(root.getAttribute('data-limit') || '50', 10) || 50));
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;
      let activeRequest = null;
      let pendingRequest = null;
      let loadMoreObserver = null;
      let destroyed = false;

      const isPanelHidden = () => Boolean(
        document.hidden
        || (panel instanceof HTMLElement && (panel.hidden || panel.getAttribute('aria-hidden') === 'true'))
      );

      const currentQuery = () => String(input.value || '').trim();
      const currentFilter = () => String(filterInput ? filterInput.value : 'all').trim() || 'all';

      const updateStats = (stats) => {
        if (!stats || typeof stats !== 'object') {
          return;
        }
        Object.keys(statNodes).forEach((key) => {
          if (statNodes[key] && Object.prototype.hasOwnProperty.call(stats, key)) {
            statNodes[key].textContent = String(stats[key] ?? 0);
          }
        });
      };

      const setLoading = (value) => {
        if (loadingRow) {
          loadingRow.hidden = !value;
        }
      };

      const visibleRowCount = () => body.querySelectorAll('[data-spiritual-journey-search-row]').length;

      const setEmptyState = (message) => {
        if (!emptyRow) {
          return;
        }
        const cell = emptyRow.querySelector('td');
        if (cell && message) {
          cell.textContent = message;
        }
        emptyRow.hidden = visibleRowCount() !== 0;
      };

      const clearRows = () => {
        body.querySelectorAll('[data-spiritual-journey-search-row]').forEach((row) => {
          row.remove();
        });
      };

      const insertRows = (html) => {
        const content = String(html || '').trim();
        if (content === '') {
          return;
        }
        const anchor = loadingRow || emptyRow;
        if (anchor) {
          anchor.insertAdjacentHTML('beforebegin', content);
        } else {
          body.insertAdjacentHTML('beforeend', content);
        }
      };

      const insertTemplates = (html, mode) => {
        if (!templatesContainer) {
          return;
        }
        if (mode === 'replace') {
          templatesContainer.innerHTML = '';
        }
        const content = String(html || '').trim();
        if (content !== '') {
          templatesContainer.insertAdjacentHTML('beforeend', content);
        }
      };

      const syncUrl = () => {
        if (!window.history || typeof window.history.replaceState !== 'function') {
          return;
        }
        const url = new URL(window.location.href);
        const query = currentQuery();
        const filter = currentFilter();
        if (query) {
          url.searchParams.set('q', query);
        } else {
          url.searchParams.delete('q');
        }
        if (filter !== 'all') {
          url.searchParams.set('journey_filter', filter);
        } else {
          url.searchParams.delete('journey_filter');
        }
        url.searchParams.delete('page');
        url.searchParams.delete('per_page');
        url.searchParams.delete('cursor');
        url.searchParams.delete('limit');
        url.searchParams.delete('edit');
        url.searchParams.delete('view');
        window.history.replaceState(window.history.state, '', url.toString());
        window.dispatchEvent(new CustomEvent('discipleship:panel-url-change', {
          detail: { tabKey: 'spiritual', url: url.toString() }
        }));
      };

      const buildRowsUrl = (cursor) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]') || panel.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('journey_filter', currentFilter());
        if (cursor) {
          url.searchParams.set('cursor', cursor);
        }
        url.searchParams.set('limit', String(limit));
        if (branchInput && String(branchInput.value || '').trim() !== '') {
          url.searchParams.set('branch_id', String(branchInput.value).trim());
        }

        return url;
      };

      const applyResult = (data, mode) => {
        if (mode === 'replace') {
          clearRows();
        }
        insertRows(data && data.html);
        insertTemplates(data && data.templates_html, mode);
        hasMore = Boolean(data && data.has_more);
        nextCursor = hasMore ? (String(data && data.next_cursor || '').trim() || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-cursor', nextCursor || '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Peserta tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadCursor = (cursor, mode) => {
        if (isPanelHidden()) {
          pendingRequest = { cursor, mode, syncUrl: false };
          return;
        }
        if (mode === 'append' && (!hasMore || !cursor || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const controller = activeController;
        const requestId = ++requestSeq;
        activeRequest = { cursor, mode };
        pendingRequest = null;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(cursor).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error('Gagal memuat spiritual journey.');
            }

            return response.json();
          })
          .then((data) => {
            if (requestId !== requestSeq) {
              return;
            }
            applyResult(data, mode);
            window.requestAnimationFrame(loadMoreIfNeeded);
          })
          .catch((error) => {
            if (error && error.name === 'AbortError') {
              return;
            }
            if (mode === 'replace') {
              clearRows();
              setEmptyState('Data spiritual journey gagal dimuat.');
            }
          })
          .finally(() => {
            if (requestId !== requestSeq) {
              return;
            }
            isLoading = false;
            activeRequest = null;
            if (activeController === controller) {
              activeController = null;
            }
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (!isPanelHidden() && hasMore && nextCursor && !isLoading && nearBottom()) {
          loadCursor(nextCursor, 'append');
        }
      };

      input.addEventListener('input', scheduleSearch);
      if (filterInput) {
        filterInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          if (isPanelHidden()) {
            pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
            return;
          }
          syncUrl();
          loadCursor(null, 'replace');
        });
      }
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
        }
        if (isPanelHidden()) {
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
          return;
        }
        syncUrl();
        loadCursor(null, 'replace');
      });

      const sentinel = document.createElement('div');
      sentinel.className = 'lazy-list-sentinel';
      sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'height:1px;width:100%;pointer-events:none';
      (tableScrollArea || root).appendChild(sentinel);
      if ('IntersectionObserver' in window) {
        loadMoreObserver = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            loadMoreIfNeeded();
          }
        }, {
          root: scrollArea instanceof HTMLElement ? scrollArea : null,
          rootMargin: '180px 0px'
        });
        loadMoreObserver.observe(sentinel);
      } else {
        if (scrollArea) {
          scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        }
        window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
        window.addEventListener('resize', loadMoreIfNeeded);
      }

      const suspendPanel = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
          pendingRequest = { cursor: null, mode: 'replace', syncUrl: true };
        }
        if (activeController) {
          if (!pendingRequest && activeRequest) {
            pendingRequest = { ...activeRequest, syncUrl: false };
          }
          requestSeq += 1;
          activeController.abort();
          activeController = null;
          activeRequest = null;
          isLoading = false;
          setLoading(false);
        }
      };
      const resumePanel = () => {
        if (destroyed || isPanelHidden()) {
          return;
        }
        if (pendingRequest) {
          const request = pendingRequest;
          pendingRequest = null;
          if (request.syncUrl) {
            syncUrl();
          }
          loadCursor(request.cursor, request.mode);
          return;
        }
        window.requestAnimationFrame(loadMoreIfNeeded);
      };
      const handleVisibilityChange = () => {
        if (document.hidden) {
          suspendPanel();
        } else {
          resumePanel();
        }
      };
      panel.addEventListener('discipleship:panel-deactivate', suspendPanel);
      panel.addEventListener('discipleship:panel-activate', resumePanel);
      document.addEventListener('visibilitychange', handleVisibilityChange);
      panel.addEventListener('discipleship:panel-destroy', () => {
        suspendPanel();
        destroyed = true;
        pendingRequest = null;
        if (loadMoreObserver) {
          loadMoreObserver.disconnect();
          loadMoreObserver = null;
        } else {
          if (scrollArea) {
            scrollArea.removeEventListener('scroll', loadMoreIfNeeded);
          }
          window.removeEventListener('scroll', loadMoreIfNeeded);
          window.removeEventListener('resize', loadMoreIfNeeded);
        }
        document.removeEventListener('visibilitychange', handleVisibilityChange);
      });
      setEmptyState('Peserta tidak ditemukan.');
    };

    const setupHorizontalTableScroll = (scope = document) => {
      const areas = scope.matches?.('[data-table-horizontal-scroll]')
        ? [scope]
        : Array.from(scope.querySelectorAll?.('[data-table-horizontal-scroll]') || []);

      areas.forEach((area) => {
        if (area.getAttribute('data-table-horizontal-scroll-ready') === '1') return;
        area.setAttribute('data-table-horizontal-scroll-ready', '1');

        let dragging = false;
        let moved = false;
        let startX = 0;
        let startLeft = 0;

        const finish = () => {
          if (!dragging) return;
          dragging = false;
          area.classList.remove('is-dragging');
          if (moved) {
            area.addEventListener('click', (event) => {
              event.preventDefault();
              event.stopPropagation();
            }, { capture: true, once: true });
          }
        };

        area.addEventListener('mousedown', (event) => {
          if (event.button !== 0 || event.target.closest('button, a, input, select, textarea, label')) return;
          dragging = true;
          moved = false;
          startX = event.clientX;
          startLeft = area.scrollLeft;
          area.classList.add('is-dragging');
        });
        window.addEventListener('mousemove', (event) => {
          if (!dragging) return;
          const delta = event.clientX - startX;
          if (Math.abs(delta) > 3) moved = true;
          area.scrollLeft = startLeft - delta;
        });
        window.addEventListener('mouseup', finish);
        area.addEventListener('mouseleave', finish);
      });
    };

    setupHorizontalTableScroll();
    setupSpiritualJourneyList();
    setupDiscipleshipDashboard();
    setupDiscipleshipPeopleList();
    setupDiscipleshipGroupsList();
    setupMskList();

    const memberFeedbackGroupModal = document.querySelector('[data-member-feedback-group-modal]');
    if (memberFeedbackGroupModal) {
      const titleEl = memberFeedbackGroupModal.querySelector('[data-member-feedback-group-title]');
      const bodyEl = memberFeedbackGroupModal.querySelector('[data-member-feedback-group-body]');
      const closeButtons = memberFeedbackGroupModal.querySelectorAll('[data-member-feedback-group-close]');
      const templateMap = new Map();

      document.querySelectorAll('template[data-member-feedback-group-template]').forEach((templateEl) => {
        const groupSessionId = templateEl.getAttribute('data-member-feedback-group-template') || '';
        if (groupSessionId) {
          templateMap.set(groupSessionId, templateEl);
        }
      });

      const openMemberFeedbackGroup = (groupSessionId) => {
        const key = String(groupSessionId || '').trim();
        if (!key || !templateMap.has(key)) {
          return;
        }

        const templateEl = templateMap.get(key);
        if (titleEl) {
          titleEl.textContent = templateEl?.getAttribute('data-member-feedback-group-template-title') || 'Feedback Kelompok';
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateEl ? templateEl.innerHTML : '';
        }

        memberFeedbackGroupModal.classList.add('is-open');
        memberFeedbackGroupModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };

      const closeMemberFeedbackGroup = () => {
        memberFeedbackGroupModal.classList.remove('is-open');
        memberFeedbackGroupModal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-member-feedback-group-open]');
        if (!trigger) {
          return;
        }

        event.preventDefault();
        openMemberFeedbackGroup(trigger.getAttribute('data-member-feedback-group-open') || '');
      });

      closeButtons.forEach((button) => {
        button.addEventListener('click', function () {
          closeMemberFeedbackGroup();
        });
      });

      memberFeedbackGroupModal.addEventListener('click', function (event) {
        if (event.target === memberFeedbackGroupModal) {
          closeMemberFeedbackGroup();
        }
      });

      document.addEventListener('keydown', function (event) {
        const detailModalOpen = Boolean(document.querySelector('[data-member-feedback-detail-modal].is-open'));
        if (event.key === 'Escape' && memberFeedbackGroupModal.classList.contains('is-open') && !detailModalOpen) {
          closeMemberFeedbackGroup();
        }
      });
    }

    const memberFeedbackDetailModal = document.querySelector('[data-member-feedback-detail-modal]');
    if (memberFeedbackDetailModal) {
      const titleEl = memberFeedbackDetailModal.querySelector('[data-member-feedback-detail-title]');
      const bodyEl = memberFeedbackDetailModal.querySelector('[data-member-feedback-detail-body]');
      const closeButtons = memberFeedbackDetailModal.querySelectorAll('[data-member-feedback-detail-close]');
      const templateMap = new Map();

      document.querySelectorAll('template[data-member-feedback-detail-template]').forEach((templateEl) => {
        const feedbackId = templateEl.getAttribute('data-member-feedback-detail-template') || '';
        if (feedbackId) {
          templateMap.set(feedbackId, templateEl);
        }
      });

      const openMemberFeedbackDetail = (feedbackId) => {
        const key = String(feedbackId || '').trim();
        if (!key || !templateMap.has(key)) {
          return;
        }

        const templateEl = templateMap.get(key);
        if (titleEl) {
          titleEl.textContent = templateEl?.getAttribute('data-member-feedback-detail-template-title') || 'Detail Feedback';
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateEl ? templateEl.innerHTML : '';
        }

        memberFeedbackDetailModal.classList.add('is-open');
        memberFeedbackDetailModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };

      const closeMemberFeedbackDetail = () => {
        memberFeedbackDetailModal.classList.remove('is-open');
        memberFeedbackDetailModal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-member-feedback-detail-open]');
        if (!trigger) {
          return;
        }

        event.preventDefault();
        openMemberFeedbackDetail(trigger.getAttribute('data-member-feedback-detail-open') || '');
      });

      closeButtons.forEach((button) => {
        button.addEventListener('click', function () {
          closeMemberFeedbackDetail();
        });
      });

      memberFeedbackDetailModal.addEventListener('click', function (event) {
        if (event.target === memberFeedbackDetailModal) {
          closeMemberFeedbackDetail();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && memberFeedbackDetailModal.classList.contains('is-open')) {
          closeMemberFeedbackDetail();
        }
      });
    }

    const mskViewModalCandidate = document.querySelector('[data-msk-view-modal]');
    const mskViewModal = mskViewModalCandidate
      && !mskViewModalCandidate.closest('[data-discipleship-workspace]')
      ? mskViewModalCandidate
      : null;
    if (mskViewModal) {
      const titleEl = mskViewModal.querySelector('[data-msk-view-title]');
      const bodyEl = mskViewModal.querySelector('[data-msk-view-body]');
      const editLinkEl = mskViewModal.querySelector('[data-msk-view-edit-link]');
      const closeButtons = mskViewModal.querySelectorAll('[data-msk-view-close]');
      const templateMap = new Map();

      document.querySelectorAll('template[data-msk-view-template]').forEach((templateEl) => {
        const participantId = templateEl.getAttribute('data-msk-view-template') || '';
        if (participantId) {
          templateMap.set(participantId, templateEl);
        }
      });

      const openMskView = (participantId) => {
        const key = String(participantId || '').trim();
        if (!key) {
          return false;
        }
        const freshTemplate = queryTemplateByAttribute('data-msk-view-template', key);
        if (freshTemplate) {
          templateMap.set(key, freshTemplate);
        }
        if (!templateMap.has(key)) {
          return false;
        }
        const templateEl = templateMap.get(key);
        const modalTitle = templateEl?.getAttribute('data-msk-view-template-title') || 'Detail Peserta MSK';
        const editHref = templateEl?.getAttribute('data-msk-view-template-edit') || '?page=msk_classes';
        const templateHtml = templateEl ? templateEl.innerHTML : '';

        if (titleEl) {
          titleEl.textContent = modalTitle;
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateHtml;
        }
        if (editLinkEl) {
          if (editHref && editHref !== '?page=msk_classes') {
            editLinkEl.setAttribute('href', editHref);
            editLinkEl.classList.remove('is-hidden');
          } else {
            editLinkEl.removeAttribute('href');
            editLinkEl.classList.add('is-hidden');
          }
        }

        mskViewModal.classList.add('is-open');
        mskViewModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        return true;
      };

      const closeMskView = () => {
        mskViewModal.classList.remove('is-open');
        mskViewModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-msk-view-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        const opened = openMskView(trigger.getAttribute('data-msk-view-open') || '');
        if (!opened && trigger.getAttribute('data-msk-view-href')) {
          window.location.href = trigger.getAttribute('data-msk-view-href');
        }
      });

      closeButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
          closeMskView();
        });
      });

      mskViewModal.addEventListener('click', function (event) {
        if (event.target === mskViewModal) {
          closeMskView();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && mskViewModal.classList.contains('is-open')) {
          closeMskView();
        }
      });

      const autoOpenId = mskViewModal.getAttribute('data-msk-view-auto-open') || '';
      if (autoOpenId) {
        openMskView(autoOpenId);
      }
    }

    const mskEditModalCandidate = document.querySelector('[data-msk-edit-modal]');
    const mskEditModal = mskEditModalCandidate
      && !mskEditModalCandidate.closest('[data-discipleship-dashboard-panel]')
      && !mskEditModalCandidate.closest('[data-discipleship-workspace]')
      ? mskEditModalCandidate
      : null;
    if (mskEditModal) {
      const titleEl = mskEditModal.querySelector('[data-msk-edit-title]');
      const bodyEl = mskEditModal.querySelector('[data-msk-edit-body]');
      const templateMap = new Map();
      const clearMskEditSelectionAlerts = () => {
        document.querySelectorAll('.alert.danger').forEach((alertEl) => {
          const message = String(alertEl.textContent || '').trim();
          if (
            message === 'Data peserta kelas MSK yang ingin diedit tidak ditemukan.' ||
            message === 'Data peserta kelas MSK yang ingin dilihat tidak ditemukan.'
          ) {
            alertEl.remove();
          }
        });
      };

      document.querySelectorAll('template[data-msk-edit-template]').forEach((templateEl) => {
        const participantId = templateEl.getAttribute('data-msk-edit-template') || '';
        if (participantId) {
          templateMap.set(participantId, templateEl);
        }
      });

      const openMskEdit = (participantId) => {
        const key = String(participantId || '').trim();
        if (!key) {
          return false;
        }
        const freshTemplate = queryTemplateByAttribute('data-msk-edit-template', key);
        if (freshTemplate) {
          templateMap.set(key, freshTemplate);
        }
        if (!templateMap.has(key)) {
          return false;
        }
        const templateEl = templateMap.get(key);
        const modalTitle = templateEl?.getAttribute('data-msk-edit-template-title') || 'Edit Peserta MSK';
        const templateHtml = templateEl ? templateEl.innerHTML : '';

        if (titleEl) {
          titleEl.textContent = modalTitle;
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateHtml;
          initMskForms(bodyEl);
        }

        mskEditModal.classList.add('is-open');
        mskEditModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        clearMskEditSelectionAlerts();

        if (bodyEl) {
          const firstInput = bodyEl.querySelector('input, select, textarea');
          if (firstInput && typeof firstInput.focus === 'function') {
            firstInput.focus();
          }
        }

        return true;
      };

      const closeMskEdit = () => {
        mskEditModal.classList.remove('is-open');
        mskEditModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-msk-edit-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        const opened = openMskEdit(trigger.getAttribute('data-msk-edit-open') || '');
        if (!opened && trigger.getAttribute('data-msk-edit-href')) {
          window.location.href = trigger.getAttribute('data-msk-edit-href');
        }
      });

      mskEditModal.addEventListener('click', function (event) {
        if (event.target === mskEditModal) {
          closeMskEdit();
          return;
        }
        const closeTrigger = event.target.closest('[data-msk-edit-close]');
        if (closeTrigger) {
          event.preventDefault();
          closeMskEdit();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && mskEditModal.classList.contains('is-open')) {
          closeMskEdit();
        }
      });

      const autoOpenId = mskEditModal.getAttribute('data-msk-edit-auto-open') || '';
      if (autoOpenId) {
        openMskEdit(autoOpenId);
      }
    }

    const spiritualJourneyViewModalCandidate = document.querySelector('[data-spiritual-journey-view-modal]');
    const spiritualJourneyViewModal = spiritualJourneyViewModalCandidate
      && !spiritualJourneyViewModalCandidate.closest('[data-discipleship-workspace]')
      ? spiritualJourneyViewModalCandidate
      : null;
    if (spiritualJourneyViewModal) {
      const titleEl = spiritualJourneyViewModal.querySelector('[data-spiritual-journey-view-title]');
      const bodyEl = spiritualJourneyViewModal.querySelector('[data-spiritual-journey-view-body]');
      const closeButtons = spiritualJourneyViewModal.querySelectorAll('[data-spiritual-journey-view-close]');
      const templateMap = new Map();

      document.querySelectorAll('template[data-spiritual-journey-view-template]').forEach((templateEl) => {
        const personKey = templateEl.getAttribute('data-spiritual-journey-view-template') || '';
        if (personKey) {
          templateMap.set(personKey, templateEl);
        }
      });

      const openSpiritualJourneyView = (personKey) => {
        const key = String(personKey || '').trim();
        if (!key) {
          return false;
        }
        if (!templateMap.has(key)) {
          const freshTemplate = queryTemplateByAttribute('data-spiritual-journey-view-template', key);
          if (freshTemplate) {
            templateMap.set(key, freshTemplate);
          }
        }
        if (!templateMap.has(key)) {
          return false;
        }
        const templateEl = templateMap.get(key);
        const modalTitle = templateEl?.getAttribute('data-spiritual-journey-view-template-title') || 'Profil Spiritual';
        const templateHtml = templateEl ? templateEl.innerHTML : '';

        if (titleEl) {
          titleEl.textContent = modalTitle;
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateHtml;
        }

        spiritualJourneyViewModal.classList.add('is-open');
        spiritualJourneyViewModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        return true;
      };

      const closeSpiritualJourneyView = () => {
        spiritualJourneyViewModal.classList.remove('is-open');
        spiritualJourneyViewModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-spiritual-journey-view-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        openSpiritualJourneyView(trigger.getAttribute('data-spiritual-journey-view-open') || '');
      });

      closeButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
          closeSpiritualJourneyView();
        });
      });

      spiritualJourneyViewModal.addEventListener('click', function (event) {
        if (event.target === spiritualJourneyViewModal) {
          closeSpiritualJourneyView();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && spiritualJourneyViewModal.classList.contains('is-open')) {
          closeSpiritualJourneyView();
        }
      });
    }

    const setupDiscipleshipTreeHistory = (scope) => {
      const treeV2HistoryModal = scope.querySelector('[data-tree-v2-history-modal]');
      if (!treeV2HistoryModal) {
        return;
      }
      const titleEl = treeV2HistoryModal.querySelector('[data-tree-v2-history-title]');
      const bodyEl = treeV2HistoryModal.querySelector('[data-tree-v2-history-body]');
      const closeButtons = treeV2HistoryModal.querySelectorAll('[data-tree-v2-history-close]');
      const detailUrlTemplate = scope.getAttribute('data-tree-group-detail-url-template') || '';
      const detailCache = new Map();
      let activeController = null;
      let activeGroupKey = '';
      let requestSequence = 0;

      const detailUrl = (groupKey) => {
        const raw = detailUrlTemplate.replace('__id__', encodeURIComponent(groupKey));
        const url = new URL(raw, window.location.origin);
        const current = new URL(window.location.href);
        if (current.searchParams.has('branch_id')) {
          url.searchParams.set('branch_id', current.searchParams.get('branch_id') || 'all');
        }
        return url.toString();
      };

      const renderHistory = (data) => {
        if (titleEl) {
          titleEl.textContent = String(data && data.title || 'Riwayat Kelompok');
        }
        if (bodyEl) {
          bodyEl.innerHTML = String(data && data.html || '<div class="journey-history-empty">Riwayat kelompok belum tersedia.</div>');
        }
      };

      const loadHistory = (groupKey, force = false) => {
        const key = String(groupKey || '').trim();
        if (!key || !detailUrlTemplate) {
          return;
        }
        activeGroupKey = key;
        const cached = !force ? detailCache.get(key) : null;
        if (cached) {
          renderHistory(cached);
          return;
        }
        if (bodyEl) {
          bodyEl.innerHTML = '<div class="panel-note" role="status">Memuat riwayat kelompok...</div>';
        }
        if (titleEl) titleEl.textContent = 'Riwayat Kelompok';

        if (activeController) activeController.abort();
        activeController = new AbortController();
        const controller = activeController;
        const requestId = ++requestSequence;
        window.fetch(detailUrl(key), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        }).then((response) => {
          if (!response.ok) throw new Error('detail request failed');
          return response.json();
        }).then((data) => {
          detailCache.set(key, data);
          if (requestId === requestSequence && activeGroupKey === key) {
            renderHistory(data);
          }
        }).catch((error) => {
          if (error && error.name === 'AbortError') return;
          if (requestId === requestSequence && activeGroupKey === key && bodyEl) {
            bodyEl.innerHTML = '<div class="panel-note">Riwayat gagal dimuat. <button class="btn tiny secondary" type="button" data-tree-history-retry>Coba lagi</button></div>';
          }
        }).finally(() => {
          if (activeController === controller) activeController = null;
        });
      };

      const openTreeV2History = (groupKey) => {
        const key = String(groupKey || '').trim();
        if (!key) return;

        treeV2HistoryModal.classList.add('is-open');
        treeV2HistoryModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        loadHistory(key);
      };

      const closeTreeV2History = () => {
        if (activeController) {
          activeController.abort();
          activeController = null;
        }
        treeV2HistoryModal.classList.remove('is-open');
        treeV2HistoryModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      scope.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-tree-v2-history-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        openTreeV2History(trigger.getAttribute('data-tree-v2-history-open') || '');
      });

      treeV2HistoryModal.addEventListener('click', function (event) {
        const retry = event.target.closest('[data-tree-history-retry]');
        if (retry && activeGroupKey) {
          event.preventDefault();
          loadHistory(activeGroupKey, true);
        }
      });

      scope.addEventListener('keydown', function (event) {
        if (!event || (event.key !== 'Enter' && event.key !== ' ')) return;
        const target = event.target;
        if (!target || !target.matches || !target.matches('[data-tree-v2-history-open]')) return;
        event.preventDefault();
        openTreeV2History(target.getAttribute('data-tree-v2-history-open') || '');
      });

      closeButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
          closeTreeV2History();
        });
      });

      treeV2HistoryModal.addEventListener('click', function (event) {
        if (event.target === treeV2HistoryModal) {
          closeTreeV2History();
        }
      });

      scope.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && treeV2HistoryModal.classList.contains('is-open')) {
          closeTreeV2History();
        }
      });

      scope.addEventListener('discipleship:tree-mutated', function () {
        detailCache.clear();
      });
      scope.addEventListener('discipleship:panel-destroy', function () {
        detailCache.clear();
        if (activeController) activeController.abort();
        activeController = null;
      }, { once: true });
    };

    const mskCreateModalCandidate = document.querySelector('[data-msk-create-modal]');
    const mskCreateModal = mskCreateModalCandidate
      && !mskCreateModalCandidate.closest('[data-discipleship-workspace]')
      ? mskCreateModalCandidate
      : null;
    if (mskCreateModal) {
      const openMskCreate = () => {
        mskCreateModal.classList.add('is-open');
        mskCreateModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        initMskForms(mskCreateModal);

        const firstInput = mskCreateModal.querySelector('input, select, textarea');
        if (firstInput && typeof firstInput.focus === 'function') {
          firstInput.focus();
        }
      };

      const closeMskCreate = () => {
        mskCreateModal.classList.remove('is-open');
        mskCreateModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-msk-create-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        openMskCreate();
      });

      mskCreateModal.addEventListener('click', function (event) {
        if (event.target === mskCreateModal) {
          closeMskCreate();
          return;
        }
        const closeTrigger = event.target.closest('[data-msk-create-close]');
        if (closeTrigger) {
          event.preventDefault();
          closeMskCreate();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && mskCreateModal.classList.contains('is-open')) {
          closeMskCreate();
        }
      });
    }

    const worshipAttendanceModal = document.querySelector('[data-worship-attendance-modal]');
    if (worshipAttendanceModal) {
      const titleEl = worshipAttendanceModal.querySelector('[data-worship-attendance-title]');
      const bodyEl = worshipAttendanceModal.querySelector('[data-worship-attendance-body]');
      const templateMap = new Map();

      document.querySelectorAll('template[data-worship-attendance-template]').forEach((templateEl) => {
        const reportId = templateEl.getAttribute('data-worship-attendance-template') || '';
        if (reportId) {
          templateMap.set(reportId, templateEl);
        }
      });

      const openWorshipAttendance = (reportId) => {
        const key = String(reportId || '').trim();
        if (!key || !templateMap.has(key)) {
          return;
        }
        const templateEl = templateMap.get(key);
        const modalTitle = templateEl?.getAttribute('data-worship-attendance-template-title') || 'Isi Kehadiran';
        const templateHtml = templateEl ? templateEl.innerHTML : '';

        if (titleEl) {
          titleEl.textContent = modalTitle;
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateHtml;
        }

        worshipAttendanceModal.classList.add('is-open');
        worshipAttendanceModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        if (bodyEl) {
          const firstInput = bodyEl.querySelector('input, select, textarea');
          if (firstInput && typeof firstInput.focus === 'function') {
            firstInput.focus();
          }
        }
      };

      const closeWorshipAttendance = () => {
        worshipAttendanceModal.classList.remove('is-open');
        worshipAttendanceModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-worship-attendance-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        openWorshipAttendance(trigger.getAttribute('data-worship-attendance-open') || '');
      });

      worshipAttendanceModal.addEventListener('click', function (event) {
        if (event.target === worshipAttendanceModal) {
          closeWorshipAttendance();
          return;
        }
        const closeTrigger = event.target.closest('[data-worship-attendance-close]');
        if (closeTrigger) {
          event.preventDefault();
          closeWorshipAttendance();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && worshipAttendanceModal.classList.contains('is-open')) {
          closeWorshipAttendance();
        }
      });

      const autoOpenId = worshipAttendanceModal.getAttribute('data-worship-attendance-auto-open') || '';
      if (autoOpenId) {
        openWorshipAttendance(autoOpenId);
      }
    }

    function setupMskForm(mskForm) {
      if (!mskForm || mskForm.getAttribute('data-msk-form-ready') === '1') {
        return;
      }

      mskForm.setAttribute('data-msk-form-ready', '1');
    }

    function initMskForms(scope = document) {
      if (!scope) {
        return;
      }
      if (scope.matches && scope.matches('[data-msk-form]')) {
        setupMskForm(scope);
      }
      if (scope.querySelectorAll) {
        scope.querySelectorAll('[data-msk-form]').forEach((formEl) => {
          setupMskForm(formEl);
        });
      }
    }

    initMskForms(document);

    document.querySelectorAll('[data-discipleship-branch-filter]').forEach((select) => {
      if (select.dataset.branchFilterReady === '1') {
        return;
      }
      select.dataset.branchFilterReady = '1';
      select.addEventListener('change', () => {
        let nextUrl;
        try {
          nextUrl = new URL(window.location.href);
        } catch (_error) {
          return;
        }

        [
          'page',
          'per_page',
          'cursor',
          'limit',
          'rekap_cabang',
          'edit',
          'view',
          'error',
          'conflict',
          'left_group',
          'person_archived',
          'group_completed',
          'group_reactivated',
          'edit_msk_sessions',
          'msk_session_saved',
          'converted',
          'imported',
          'import_msk_inserted',
          'import_msk_updated',
          'import_msk_unchanged',
          'import_error_count',
          'import_error_preview'
        ].forEach((param) => nextUrl.searchParams.delete(param));

        const branchId = String(select.value || '').trim();
        if (branchId !== '') {
          nextUrl.searchParams.set('branch_id', branchId);
        } else {
          nextUrl.searchParams.delete('branch_id');
        }
        window.location.assign(nextUrl.toString());
      });
    });

    const appShell = document.querySelector('.app-shell');
    const sidebar = appShell ? appShell.querySelector('.sidebar') : null;
    const sidebarToggle = appShell ? appShell.querySelector('[data-sidebar-toggle]') : null;
    const sidebarBackdrop = appShell ? appShell.querySelector('[data-sidebar-backdrop]') : null;
    const sidebarMobileQuery = window.matchMedia('(max-width: 1024px)');

    if (appShell && sidebar && sidebarToggle && sidebarBackdrop) {
      const setSidebarOpen = (open) => {
        const shouldOpen = Boolean(open) && sidebarMobileQuery.matches;
        appShell.classList.toggle('sidebar-open', shouldOpen);
        document.body.classList.toggle('sidebar-open', shouldOpen);
        sidebarToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      };

      sidebarToggle.addEventListener('click', function () {
        const isOpen = appShell.classList.contains('sidebar-open');
        setSidebarOpen(!isOpen);
      });

      sidebarBackdrop.addEventListener('click', function () {
        setSidebarOpen(false);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && appShell.classList.contains('sidebar-open')) {
          setSidebarOpen(false);
        }
      });

      const closeSidebarAfterNavigate = sidebar.querySelectorAll('a, form button[type="submit"]');
      closeSidebarAfterNavigate.forEach(el => {
        el.addEventListener('click', function () {
          if (sidebarMobileQuery.matches) {
            setSidebarOpen(false);
          }
        });
      });

      const handleViewportChange = () => {
        if (!sidebarMobileQuery.matches) {
          setSidebarOpen(false);
        }
      };

      if (typeof sidebarMobileQuery.addEventListener === 'function') {
        sidebarMobileQuery.addEventListener('change', handleViewportChange);
      } else if (typeof sidebarMobileQuery.addListener === 'function') {
        sidebarMobileQuery.addListener(handleViewportChange);
      }

      handleViewportChange();
    }

    const setupDiscipleshipTreePeopleModal = (scope) => {
      const modal = scope.querySelector('[data-modal]');
      if (!modal) {
        return;
      }
      const titleEl = modal.querySelector('[data-modal-title]');
      const addForm = modal.querySelector('[data-modal-form="add"]');
      const editForm = modal.querySelector('[data-modal-form="edit"]');
      const rootNote = modal.querySelector('[data-root-note]');
      const closeButtons = modal.querySelectorAll('[data-modal-close]');

      const getData = (btn, key) => {
        if (!btn || !btn.dataset) return '';
        return btn.dataset[key] || '';
      };

      const filterMemberOptions = (selectEl, currentPersonId) => {
        if (!selectEl) return;
        Array.from(selectEl.options).forEach(opt => {
          if (opt.value === '') {
            opt.hidden = false;
            opt.disabled = false;
            return;
          }
          const assignedPersonId = opt.getAttribute('data-person-id') || '';
          const isBlocked = assignedPersonId !== '' && assignedPersonId !== currentPersonId;
          opt.hidden = isBlocked;
          opt.disabled = isBlocked;
          if (isBlocked) opt.selected = false;
        });
      };

      const syncAddMemberRows = (pickerEl) => {
        if (!pickerEl) return;
        const rowEls = Array.from(pickerEl.querySelectorAll('[data-add-member-row]'));
        const selectedValues = rowEls
          .map((rowEl) => {
            const selectEl = rowEl.querySelector('select[name="member_ids[]"]');
            return selectEl ? String(selectEl.value || '') : '';
          })
          .filter((value) => value !== '');

        rowEls.forEach((rowEl, index) => {
          const selectEl = rowEl.querySelector('select[name="member_ids[]"]');
          const removeBtn = rowEl.querySelector('[data-add-member-remove]');
          if (selectEl) {
            filterMemberOptions(selectEl, '');
            Array.from(selectEl.options).forEach((opt) => {
              if (!opt.value) return;
              const selectedElsewhere = selectedValues.includes(opt.value) && opt.value !== String(selectEl.value || '');
              if (selectedElsewhere) {
                opt.hidden = true;
                opt.disabled = true;
              }
            });
          }
          if (removeBtn) {
            removeBtn.disabled = rowEls.length === 1 && index === 0;
          }
        });
      };

      const syncAddPersonMode = (form, leaderId, groupId) => {
        if (!form) return;
        const memberSourceWrap = form.querySelector('[data-add-member-source-wrap]');
        const memberPicker = form.querySelector('[data-add-member-picker]');
        const externalNameWrap = form.querySelector('[data-add-external-name-wrap]');
        const externalNameInput = form.querySelector('input[name="full_name"]');
        const memberSelects = form.querySelectorAll('select[name="member_ids[]"]');
        const appendBtn = form.querySelector('[data-add-member-append]');
        const isExternalRoot = String(leaderId || '').trim() === 'virtual_injil' && String(groupId || '').trim() === '';

        if (memberSourceWrap) {
          memberSourceWrap.classList.toggle('is-hidden', isExternalRoot);
        }
        if (externalNameWrap) {
          externalNameWrap.classList.toggle('is-hidden', !isExternalRoot);
        }
        memberSelects.forEach((selectEl) => {
          if (isExternalRoot) {
            selectEl.value = '';
            selectEl.required = false;
          } else {
            selectEl.required = true;
          }
        });
        if (appendBtn) {
          appendBtn.classList.toggle('is-hidden', isExternalRoot);
        }
        if (externalNameInput) {
          externalNameInput.required = isExternalRoot;
          if (!isExternalRoot) {
            externalNameInput.value = '';
          }
        }
        if (!isExternalRoot && memberPicker) {
          syncAddMemberRows(memberPicker);
        }
      };

      const openModal = (mode, btn) => {
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');

        if (mode === 'add') {
          if (titleEl) titleEl.textContent = 'Tambah Anggota';
          if (addForm) {
            addForm.classList.remove('is-hidden');
            addForm.reset();
            const leaderSelect = addForm.querySelector('select[name="leader_id"]');
            const leaderInput = addForm.querySelector('input[name="leader_id"]');
            const groupInput = addForm.querySelector('input[name="group_id"]');
            const memberPicker = addForm.querySelector('[data-add-member-picker]');
            const memberList = addForm.querySelector('[data-add-member-list]');
            const externalNameInput = addForm.querySelector('input[name="full_name"]');
            const leaderId = getData(btn, 'parentId');
            const groupId = getData(btn, 'groupId');
            if (groupInput) groupInput.value = groupId;
            if (leaderSelect) leaderSelect.value = leaderId;
            if (leaderSelect) leaderSelect.disabled = groupId !== '';
            if (leaderInput) leaderInput.value = leaderId;
            if (memberPicker && memberList) {
              const rowEls = Array.from(memberList.querySelectorAll('[data-add-member-row]'));
              rowEls.forEach((rowEl, index) => {
                if (index > 0) {
                  rowEl.remove();
                }
              });
              const firstSelect = memberList.querySelector('select[name="member_ids[]"]');
              if (firstSelect) {
                firstSelect.value = '';
              }
              syncAddMemberRows(memberPicker);
            }
            if (externalNameInput) {
              externalNameInput.value = '';
            }
            syncAddPersonMode(addForm, leaderId, groupId);
          }
          if (editForm) editForm.classList.add('is-hidden');
          if (addForm) {
            const memberInput = addForm.querySelector('select[name="member_ids[]"]');
            const nameInput = addForm.querySelector('input[name="full_name"]');
            const groupInput = addForm.querySelector('input[name="group_id"]');
            const leaderInput = addForm.querySelector('input[name="leader_id"]');
            const useExternalRoot = String(leaderInput?.value || '').trim() === 'virtual_injil' && String(groupInput?.value || '').trim() === '';
            if (useExternalRoot && nameInput) {
              nameInput.focus();
            } else if (memberInput) {
              memberInput.focus();
            } else if (nameInput) {
              nameInput.focus();
            }
          }
          return;
        }

        if (titleEl) titleEl.textContent = 'Edit Data';
        if (editForm) {
          editForm.classList.remove('is-hidden');
          editForm.reset();
          const personId = getData(btn, 'personId');
          const memberId = getData(btn, 'memberId');
          const name = getData(btn, 'name');
          const phone = getData(btn, 'phone');
          const notes = getData(btn, 'notes');
          const groupId = getData(btn, 'groupId');
          const isRoot = getData(btn, 'isRoot') === '1';

          const idInput = editForm.querySelector('input[name="id"]');
          const memberSelect = editForm.querySelector('select[name="member_id"]');
          const memberInput = editForm.querySelector('input[name="member_id"]');
          const nameInput = editForm.querySelector('input[name="full_name"]');
          const phoneInput = editForm.querySelector('input[name="phone"]');
          const notesInput = editForm.querySelector('textarea[name="notes"]');
          const leaderInput = editForm.querySelector('input[name="leader_id"]');
          const groupSelect = editForm.querySelector('select[name="group_id"]');

          if (idInput) idInput.value = personId;
          if (memberSelect) {
            filterMemberOptions(memberSelect, personId);
            memberSelect.value = memberId || '';
          } else if (memberInput) {
            memberInput.value = memberId || '';
          }
          if (nameInput) nameInput.value = name;
          if (phoneInput) phoneInput.value = phone;
          if (notesInput) notesInput.value = notes;

          if (leaderInput) {
            leaderInput.value = '';
          }
          if (groupSelect) {
            groupSelect.value = groupId || '';
            groupSelect.disabled = isRoot;
          }

          if (rootNote) {
            if (isRoot) {
              rootNote.classList.remove('is-hidden');
            } else {
              rootNote.classList.add('is-hidden');
            }
          }
        }
        if (addForm) addForm.classList.add('is-hidden');
      };

      const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      const findTrigger = (el) => {
        let current = el;
        while (current && current !== scope.parentElement) {
          if (current.getAttribute && current.getAttribute('data-modal-open')) {
            return current;
          }
          current = current.parentElement;
        }
        return null;
      };

      scope.addEventListener('click', function (event) {
        const trigger = findTrigger(event.target);
        if (!trigger) return;
        event.preventDefault();
        const mode = trigger.getAttribute('data-modal-open');
        openModal(mode, trigger);
      });

      closeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
          closeModal();
        });
      });

      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal();
        }
      });

      scope.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          closeModal();
        }
      });

      const editId = modal.getAttribute('data-edit-id');
      if (editId) {
        const autoBtn = scope.querySelector('[data-modal-open="edit"][data-person-id="' + editId + '"]');
        if (autoBtn) {
          openModal('edit', autoBtn);
        }
      }

      if (addForm) {
        const memberPicker = addForm.querySelector('[data-add-member-picker]');
        const memberList = addForm.querySelector('[data-add-member-list]');
        const appendBtn = addForm.querySelector('[data-add-member-append]');

        if (memberPicker && memberList && appendBtn) {
          appendBtn.addEventListener('click', () => {
            const templateRow = memberList.querySelector('[data-add-member-row]');
            if (!templateRow) return;
            const rowEl = templateRow.cloneNode(true);
            const selectEl = rowEl.querySelector('select[name="member_ids[]"]');
            if (selectEl) {
              selectEl.value = '';
            }
            memberList.appendChild(rowEl);
            syncAddMemberRows(memberPicker);
            if (selectEl) {
              selectEl.focus();
            }
          });

          memberPicker.addEventListener('change', (event) => {
            const target = event.target;
            if (target && target.matches('select[name="member_ids[]"]')) {
              syncAddMemberRows(memberPicker);
            }
          });

          memberPicker.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
              return;
            }
            const removeBtn = target.closest('[data-add-member-remove]');
            if (!removeBtn) {
              return;
            }
            const rowEl = removeBtn.closest('[data-add-member-row]');
            if (!rowEl) return;
            const rowEls = memberList.querySelectorAll('[data-add-member-row]');
            if (rowEls.length <= 1) {
              const selectEl = rowEl.querySelector('select[name="member_ids[]"]');
              if (selectEl) {
                selectEl.value = '';
              }
            } else {
              rowEl.remove();
            }
            syncAddMemberRows(memberPicker);
          });

          syncAddMemberRows(memberPicker);
        }
      }
    };

    const setupDiscipleshipTreeGroupModal = (scope) => {
      const groupModal = scope.querySelector('[data-group-modal]');
      if (!groupModal) {
        return;
      }
      const titleEl = groupModal.querySelector('[data-group-title]');
      const addForm = groupModal.querySelector('[data-group-form="add"]');
      const editForm = groupModal.querySelector('[data-group-form="edit"]');
      const closeButtons = groupModal.querySelectorAll('[data-group-close]');
      const groupMemberSourceMap = new Map();

      scope.querySelectorAll('script[data-group-member-source]').forEach((sourceEl) => {
        const groupId = sourceEl.getAttribute('data-group-member-source') || '';
        if (!groupId) {
          return;
        }
        try {
          groupMemberSourceMap.set(groupId, JSON.parse(sourceEl.textContent || '{}'));
        } catch (_error) {
          groupMemberSourceMap.set(groupId, { members: [] });
        }
      });

      const filterAssistantOptions = (selectEl, leaderId) => {
        if (!selectEl) return;
        Array.from(selectEl.options).forEach(opt => {
          if (opt.value === '') {
            opt.hidden = false;
            opt.disabled = false;
            return;
          }
          const isLeaderOption = opt.value === leaderId;
          opt.hidden = isLeaderOption;
          opt.disabled = isLeaderOption;
          if (isLeaderOption && opt.selected) {
            opt.selected = false;
          }
        });
        if (selectEl.value === leaderId) {
          selectEl.value = '';
        }
      };

      const bindLeaderSelectToAssistant = (form) => {
        if (!form || form.dataset.groupLeaderBound === '1') {
          return;
        }
        const leaderSelect = form.querySelector('select[name="leader_id"]');
        const assistantSelect = form.querySelector('select[name="assistant_id"]');
        if (!leaderSelect || !assistantSelect) {
          return;
        }
        leaderSelect.addEventListener('change', () => {
          filterAssistantOptions(assistantSelect, String(leaderSelect.value || '').trim());
        });
        form.dataset.groupLeaderBound = '1';
      };

      const syncGroupTransitionMembers = (form) => {
        if (!form) {
          return;
        }
        const parentGroupSelect = form.querySelector('[name="parent_group_id"]');
        const transitionWrap = form.querySelector('[data-group-transition-wrap]');
        const transitionList = form.querySelector('[data-group-transition-list]');
        if (!parentGroupSelect || !transitionWrap || !transitionList) {
          return;
        }

        const parentGroupId = String(parentGroupSelect.value || '').trim();
        const sourcePayload = parentGroupId && groupMemberSourceMap.has(parentGroupId)
          ? groupMemberSourceMap.get(parentGroupId)
          : null;
        const members = sourcePayload && Array.isArray(sourcePayload.members) ? sourcePayload.members : [];
        const previousParentGroupId = form.getAttribute('data-group-transition-parent') || '';
        const selectedIds = new Set();

        if (parentGroupId !== '' && parentGroupId === previousParentGroupId) {
          transitionList.querySelectorAll('input[name="member_ids[]"]:checked').forEach((input) => {
            if (input.value) {
              selectedIds.add(input.value);
            }
          });
        } else {
          members.forEach((memberRow) => {
            if (memberRow && memberRow.id) {
              selectedIds.add(String(memberRow.id));
            }
          });
        }

        transitionList.innerHTML = '';

        if (!sourcePayload) {
          transitionWrap.classList.add('is-hidden');
          form.setAttribute('data-group-transition-parent', '');
          return;
        }

        transitionWrap.classList.remove('is-hidden');
        form.setAttribute('data-group-transition-parent', parentGroupId);

        if (members.length === 0) {
          const emptyNote = document.createElement('div');
          emptyNote.className = 'panel-note';
          const sourceStatus = sourcePayload && typeof sourcePayload.status === 'string'
            ? String(sourcePayload.status || '').trim().toLowerCase()
            : '';
          emptyNote.textContent = sourceStatus && sourceStatus !== 'active'
            ? 'Kelompok asal belum memiliki riwayat anggota.'
            : 'Kelompok asal belum memiliki anggota aktif.';
          transitionList.appendChild(emptyNote);
          return;
        }

        members.forEach((memberRow) => {
          if (!memberRow || !memberRow.id) {
            return;
          }
          const label = document.createElement('label');
          label.className = 'check-label';
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.name = 'member_ids[]';
          checkbox.value = String(memberRow.id);
          checkbox.checked = selectedIds.has(String(memberRow.id));
          label.appendChild(checkbox);
          label.appendChild(document.createTextNode(String(memberRow.name || '-')));
          transitionList.appendChild(label);
        });
      };

      const openGroupModal = (mode, btn) => {
        groupModal.classList.add('is-open');
        document.body.classList.add('modal-open');
        groupModal.setAttribute('aria-hidden', 'false');

        const leaderId = btn?.dataset.leaderId || '';
        const leaderName = btn?.dataset.leaderName || '';
        const presetParentGroupId = btn?.dataset.parentGroupId || '';
        const presetProgress = btn?.dataset.progress || '';
        const presetTitle = btn?.dataset.groupTitle || '';

        if (mode === 'add') {
          const isUpgradeFlow = String(presetParentGroupId || '').trim() !== '';
          const resolvedProgress = presetProgress || 'DG 1';
          if (titleEl) {
            titleEl.textContent = isUpgradeFlow
              ? ('Upgrade Ke ' + resolvedProgress)
              : (presetTitle || 'Tambah Kelompok');
          }
          if (addForm) {
            addForm.classList.remove('is-hidden');
            addForm.reset();
            const leaderSelect = addForm.querySelector('select[name="leader_id"]');
            const assistantSelect = addForm.querySelector('select[name="assistant_id"]');
            const progressInput = addForm.querySelector('[name="progress"]');
            const parentGroupSelect = addForm.querySelector('[name="parent_group_id"]');
            const notesInput = addForm.querySelector('textarea[name="notes"]');
            if (leaderSelect) leaderSelect.value = leaderId;
            filterAssistantOptions(assistantSelect, leaderId);
            if (assistantSelect) assistantSelect.value = '';
            if (progressInput) progressInput.value = resolvedProgress;
            if (parentGroupSelect) parentGroupSelect.value = presetParentGroupId || '';
            if (notesInput) notesInput.value = '';
            addForm.setAttribute('data-group-transition-parent', '');
            bindLeaderSelectToAssistant(addForm);
            syncGroupTransitionMembers(addForm);
          }
          if (editForm) editForm.classList.add('is-hidden');
          return;
        }

        if (titleEl) titleEl.textContent = 'Edit Kelompok';
        if (editForm) {
          editForm.classList.remove('is-hidden');
          editForm.reset();
          const groupId = btn?.dataset.groupId || '';
          const assistantId = btn?.dataset.assistantId || '';
          const progress = btn?.dataset.progress || '';
          const parentGroupId = btn?.dataset.parentGroupId || '';
          const notes = btn?.dataset.notes || '';

          const idInput = editForm.querySelector('input[name="id"]');
          const leaderSelect = editForm.querySelector('select[name="leader_id"]');
          const assistantSelect = editForm.querySelector('select[name="assistant_id"]');
          const progressSelect = editForm.querySelector('select[name="progress"]');
          const parentGroupSelect = editForm.querySelector('[name="parent_group_id"]');
          const notesInput = editForm.querySelector('textarea[name="notes"]');

          if (idInput) idInput.value = groupId;
          if (leaderSelect) leaderSelect.value = leaderId;
          filterAssistantOptions(assistantSelect, leaderId);
          if (assistantSelect) {
            assistantSelect.value = assistantId && assistantId !== leaderId ? assistantId : '';
          }
          if (progressSelect) progressSelect.value = progress || 'DG 1';
          if (parentGroupSelect) parentGroupSelect.value = parentGroupId || '';
          if (notesInput) notesInput.value = notes;
          bindLeaderSelectToAssistant(editForm);
        }
        if (addForm) addForm.classList.add('is-hidden');
      };

      const closeGroupModal = () => {
        groupModal.classList.remove('is-open');
        groupModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      scope.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-group-open]');
        if (!trigger) return;
        event.preventDefault();
        const mode = trigger.getAttribute('data-group-open');
        openGroupModal(mode, trigger);
      });

      closeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
          closeGroupModal();
        });
      });

      groupModal.addEventListener('click', function (event) {
        if (event.target === groupModal) {
          closeGroupModal();
        }
      });

      scope.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && groupModal.classList.contains('is-open')) {
          closeGroupModal();
        }
      });

      if (addForm) {
        const parentGroupSelect = addForm.querySelector('select[name="parent_group_id"]');
        if (parentGroupSelect) {
          parentGroupSelect.addEventListener('change', function () {
            syncGroupTransitionMembers(addForm);
          });
        }
        addForm.addEventListener('submit', function (event) {
          const activeParentGroupId = parentGroupSelect ? String(parentGroupSelect.value || '').trim() : '';
          if (!activeParentGroupId) {
            return;
          }
          const selectedMembers = addForm.querySelectorAll('[data-group-transition-list] input[name="member_ids[]"]:checked');
          if (selectedMembers.length > 0) {
            return;
          }
          event.preventDefault();
          window.alert('Upgrade DG harus menyisakan minimal 1 anggota yang lanjut.');
        });
      }
    };

    const setupDiscipleshipTreeActions = (scope) => {
      const treeV2ActionModal = scope.querySelector('[data-tree-v2-action-modal]');
      const treeV2HistoryModal = scope.querySelector('[data-tree-v2-history-modal]');
      const personProfileModal = scope.querySelector('[data-tree-v2-person-profile-modal]');
      if (!treeV2ActionModal && !personProfileModal) {
        return;
      }
      const titleEl = treeV2ActionModal ? treeV2ActionModal.querySelector('[data-tree-v2-action-title]') : null;
      const closeButtons = treeV2ActionModal ? treeV2ActionModal.querySelectorAll('[data-tree-v2-action-close]') : [];
      const modalActionButtons = treeV2ActionModal ? Array.from(treeV2ActionModal.querySelectorAll('[data-tree-v2-action-do]')) : [];
      const historyActionButtons = treeV2HistoryModal ? Array.from(treeV2HistoryModal.querySelectorAll('[data-tree-v2-action-do]')) : [];
      const actionButtons = modalActionButtons.concat(historyActionButtons);
      const personProfileTitleEl = personProfileModal ? personProfileModal.querySelector('[data-tree-v2-person-profile-title]') : null;
      const personProfileBodyEl = personProfileModal ? personProfileModal.querySelector('[data-tree-v2-person-profile-body]') : null;
      const personProfileCloseButtons = personProfileModal ? personProfileModal.querySelectorAll('[data-tree-v2-person-profile-close]') : [];
      const personProfileActionButtons = personProfileModal ? personProfileModal.querySelectorAll('[data-tree-v2-profile-action]') : [];
      const personDetailUrlTemplate = scope.getAttribute('data-tree-person-detail-url-template') || '';
      const personDetailCache = new Map();
      let personDetailController = null;
      let personDetailSequence = 0;
      const addMemberProxy = scope.querySelector('[data-tree-v2-proxy="add-member"]');
      const editPersonProxy = scope.querySelector('[data-tree-v2-proxy="edit-person"]');
      const addGroupProxy = scope.querySelector('[data-tree-v2-proxy="add-group"]');
      const viewHistoryProxy = scope.querySelector('[data-tree-v2-proxy="view-history"]');
      const leaveGroupForm = scope.querySelector('[data-tree-v2-leave-form]');
      const deletePersonForm = scope.querySelector('[data-tree-v2-delete-person-form]');
      const completeGroupForm = scope.querySelector('[data-tree-v2-complete-group-form]');
      const reactivateGroupForm = scope.querySelector('[data-tree-v2-reactivate-group-form]');
      const buttonsByAction = {};
      actionButtons.forEach(button => {
        const action = button.getAttribute('data-tree-v2-action-do') || '';
        if (action !== '') {
          if (!Array.isArray(buttonsByAction[action])) {
            buttonsByAction[action] = [];
          }
          buttonsByAction[action].push(button);
        }
      });
      let activeNode = null;
      let activeNodeData = null;

      const setActionVisible = (action, visible) => {
        const buttons = buttonsByAction[action];
        if (!Array.isArray(buttons)) return;
        buttons.forEach(button => {
          if (visible) {
            button.classList.remove('is-hidden');
            button.disabled = false;
          } else {
            button.classList.add('is-hidden');
            button.disabled = true;
          }
        });
      };

      const closeActionModal = (preserveContext = false) => {
        if (personDetailController) {
          personDetailController.abort();
          personDetailController = null;
        }
        if (treeV2ActionModal) {
          treeV2ActionModal.classList.remove('is-open');
          treeV2ActionModal.setAttribute('aria-hidden', 'true');
        }
        if (personProfileModal) {
          personProfileModal.classList.remove('is-open');
          personProfileModal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('modal-open');
        if (!preserveContext) {
          activeNode = null;
          activeNodeData = null;
        }
      };

      const closeHistoryModal = () => {
        if (!treeV2HistoryModal) return;
        treeV2HistoryModal.classList.remove('is-open');
        treeV2HistoryModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      const clickProxy = proxyButton => {
        if (!proxyButton) return;
        closeActionModal();
        closeHistoryModal();
        window.requestAnimationFrame(() => {
          proxyButton.click();
        });
      };

      const buildNodeData = node => {
        const dataset = node?.dataset || {};
        const kind = dataset.treeV2NodeAction || '';
        const name = (dataset.name || dataset.leaderName || '').trim() || 'Item';
        return {
          kind,
          name,
          personId: (dataset.personId || '').trim(),
          memberId: (dataset.memberId || '').trim(),
          phone: (dataset.phone || '').trim(),
          notes: dataset.notes || '',
          leader1Id: (dataset.leader1Id || '').trim(),
          isRoot: dataset.isRoot === '1',
          groupId: (dataset.groupId || '').trim(),
          leaderId: (dataset.leaderId || '').trim(),
          leaderName: (dataset.leaderName || '').trim(),
          assistantId: (dataset.assistantId || '').trim(),
          progress: (dataset.progress || '').trim(),
          status: (dataset.status || '').trim().toLowerCase(),
          parentGroupId: (dataset.parentGroupId || '').trim(),
          hasChildGroup: dataset.hasChildGroup === '1',
          members: dataset.members || '',
          isVirtual: dataset.isVirtual === '1',
          isUngrouped: dataset.isUngrouped === '1',
        };
      };

      const setProfileActionVisible = (action, visible) => {
        if (!personProfileModal) return;
        const button = personProfileModal.querySelector('[data-tree-v2-profile-action="' + action + '"]');
        if (!button) return;
        if (visible) {
          button.classList.remove('is-hidden');
          button.disabled = false;
        } else {
          button.classList.add('is-hidden');
          button.disabled = true;
        }
      };

      const currentPersonGroupContext = () => {
        const parentGroupItem = activeNode && activeNode.parentElement
          ? activeNode.parentElement.closest('.tree-v2-item-group')
          : null;
        const parentGroupNode = parentGroupItem ? parentGroupItem.firstElementChild : null;
        return {
          id: parentGroupNode && parentGroupNode.dataset
            ? String(parentGroupNode.dataset.groupId || '').trim()
            : '',
          status: parentGroupNode && parentGroupNode.dataset
            ? String(parentGroupNode.dataset.status || '').trim().toLowerCase()
            : '',
        };
      };

      const personDetailUrl = (personId) => {
        const raw = personDetailUrlTemplate.replace('__id__', encodeURIComponent(personId));
        const url = new URL(raw, window.location.origin);
        const current = new URL(window.location.href);
        if (current.searchParams.has('branch_id')) {
          url.searchParams.set('branch_id', current.searchParams.get('branch_id') || 'all');
        }
        return url.toString();
      };

      const applyPersonDetail = (personId, data) => {
        if (!activeNodeData || String(activeNodeData.personId || '') !== personId) return;
        const edit = data && typeof data.edit === 'object' ? data.edit : {};
        activeNodeData.memberId = String(edit.member_id || activeNodeData.memberId || personId).trim();
        activeNodeData.phone = String(edit.phone || '').trim();
        activeNodeData.notes = String(edit.notes || '');
        if (edit.name) activeNodeData.name = String(edit.name);

        if (personProfileTitleEl) {
          personProfileTitleEl.textContent = String(data && data.title || activeNodeData.name || 'Profil Orang');
        }
        personProfileBodyEl.innerHTML = String(data && data.html || '<div class="panel-note">Profil tidak tersedia.</div>');

        const groupContext = currentPersonGroupContext();
        setProfileActionVisible('add_group', personId !== '');
        setProfileActionVisible('edit_person', personId !== '' && Boolean(data && data.edit_url));
        setProfileActionVisible('delete_person', personId !== '' && Boolean(data && data.edit_url));
        setProfileActionVisible('leave_group', personId !== '' && Boolean(data && data.edit_url) && groupContext.id !== '' && groupContext.status === 'active');
      };

      const loadPersonDetail = (personId, force = false) => {
        const cached = !force ? personDetailCache.get(personId) : null;
        if (cached) {
          applyPersonDetail(personId, cached);
          return;
        }
        if (!personDetailUrlTemplate) return;
        if (personDetailController) personDetailController.abort();
        personDetailController = new AbortController();
        const controller = personDetailController;
        const requestId = ++personDetailSequence;
        window.fetch(personDetailUrl(personId), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        }).then((response) => {
          if (!response.ok) throw new Error('detail request failed');
          return response.json();
        }).then((data) => {
          personDetailCache.set(personId, data);
          if (requestId === personDetailSequence) applyPersonDetail(personId, data);
        }).catch((error) => {
          if (error && error.name === 'AbortError') return;
          if (requestId === personDetailSequence && activeNodeData && String(activeNodeData.personId || '') === personId) {
            personProfileBodyEl.innerHTML = '<div class="panel-note">Profil gagal dimuat. <button class="btn tiny secondary" type="button" data-tree-person-detail-retry>Coba lagi</button></div>';
          }
        }).finally(() => {
          if (personDetailController === controller) personDetailController = null;
        });
      };

      const openPersonProfileModal = (node, nodeData) => {
        if (!personProfileModal || !personProfileBodyEl) return false;
        const personId = String(nodeData.personId || '').trim();
        if (!personId || nodeData.isRoot) return false;

        activeNode = node;
        activeNodeData = nodeData;
        if (personProfileTitleEl) personProfileTitleEl.textContent = nodeData.name || 'Profil Orang';
        personProfileBodyEl.innerHTML = '<div class="panel-note" role="status">Memuat profil...</div>';
        ['add_group', 'edit_person', 'leave_group', 'delete_person'].forEach((action) => setProfileActionVisible(action, false));

        personProfileModal.classList.add('is-open');
        personProfileModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        loadPersonDetail(personId);
        return true;
      };

      const openActionModal = node => {
        if (!node) return;
        const nodeData = buildNodeData(node);
        if (nodeData.kind !== 'person' && nodeData.kind !== 'group') return;

        if (nodeData.kind === 'person' && !nodeData.isRoot && openPersonProfileModal(node, nodeData)) {
          return;
        }
        if (!treeV2ActionModal) return;

        activeNode = node;
        activeNodeData = nodeData;

        const isPerson = nodeData.kind === 'person';
        const currentGroupContext = isPerson ? currentPersonGroupContext() : { id: '', status: '' };
        const currentGroupId = currentGroupContext.id;
        const currentGroupStatus = currentGroupContext.status;
        const canAddGroup = isPerson && !nodeData.isRoot;
        const canEditPerson = isPerson && !nodeData.isRoot;
        const canDeletePerson = isPerson && !nodeData.isRoot && nodeData.personId !== '';
        const canLeaveGroup = isPerson
          && !nodeData.isRoot
          && currentGroupId !== ''
          && currentGroupStatus === 'active';
        const isActiveGroup = !isPerson && String(nodeData.status || 'active').trim() === 'active';
        const canAddMember = (!isPerson && !nodeData.isUngrouped && isActiveGroup) || (isPerson && nodeData.isRoot);
        const canViewHistory = !isPerson && nodeData.groupId !== '';
        const canCompleteGroup = !isPerson
          && !nodeData.isUngrouped
          && !nodeData.isVirtual
          && nodeData.groupId !== ''
          && isActiveGroup;
        const canReactivateGroup = !isPerson
          && !nodeData.isUngrouped
          && !nodeData.isVirtual
          && nodeData.groupId !== ''
          && !nodeData.hasChildGroup
          && String(nodeData.status || '').trim().toLowerCase() === 'completed';
        const canUpgradeGroup = !isPerson
          && !nodeData.isUngrouped
          && !nodeData.isVirtual
          && nodeData.groupId !== ''
          && isActiveGroup
          && String(nodeData.progress || '').trim() !== 'DG 3';
        const hasAnyAction = canViewHistory || canAddGroup || canEditPerson || canDeletePerson || canLeaveGroup || canAddMember || canCompleteGroup || canReactivateGroup || canUpgradeGroup;

        setActionVisible('view_history', canViewHistory);
        setActionVisible('add_group', canAddGroup);
        setActionVisible('edit_person', canEditPerson);
        setActionVisible('delete_person', canDeletePerson);
        setActionVisible('leave_group', canLeaveGroup);
        setActionVisible('add_member', canAddMember);
        setActionVisible('complete_group', canCompleteGroup);
        setActionVisible('reactivate_group', canReactivateGroup);
        setActionVisible('upgrade_group', canUpgradeGroup);

        if (titleEl) {
          titleEl.textContent = isPerson ? ('Aksi Orang: ' + nodeData.name) : ('Aksi Kelompok: ' + nodeData.name);
        }

        if (!isPerson && canViewHistory && viewHistoryProxy) {
          viewHistoryProxy.setAttribute('data-tree-v2-history-open', nodeData.groupId || '');
          viewHistoryProxy.click();
          return;
        }

        treeV2ActionModal.classList.add('is-open');
        treeV2ActionModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };

      scope.addEventListener('click', function (event) {
        const node = event.target.closest('[data-tree-v2-node-action]');
        if (!node) return;
        event.preventDefault();
        openActionModal(node);
      });

      scope.addEventListener('keydown', function (event) {
        if (!event || (event.key !== 'Enter' && event.key !== ' ')) return;
        const target = event.target;
        if (!target || !target.matches || !target.matches('[data-tree-v2-node-action]')) return;
        event.preventDefault();
        openActionModal(target);
      });

      closeButtons.forEach(button => {
        button.addEventListener('click', function () {
          closeActionModal();
        });
      });
      personProfileCloseButtons.forEach(button => {
        button.addEventListener('click', function () {
          closeActionModal();
        });
      });

      if (treeV2ActionModal) {
        treeV2ActionModal.addEventListener('click', function (event) {
          if (event.target === treeV2ActionModal) {
            closeActionModal();
          }
        });
      }
      if (personProfileModal) {
        personProfileModal.addEventListener('click', function (event) {
          const retry = event.target.closest('[data-tree-person-detail-retry]');
          if (retry && activeNodeData && activeNodeData.personId) {
            event.preventDefault();
            personProfileBodyEl.innerHTML = '<div class="panel-note" role="status">Memuat profil...</div>';
            loadPersonDetail(String(activeNodeData.personId), true);
            return;
          }
          if (event.target === personProfileModal) {
            closeActionModal();
          }
        });
      }

      scope.addEventListener('keydown', function (event) {
        if (
          event.key === 'Escape'
          && (
            (treeV2ActionModal && treeV2ActionModal.classList.contains('is-open'))
            || (personProfileModal && personProfileModal.classList.contains('is-open'))
          )
        ) {
          closeActionModal();
        }
      });

      const submitAction = actionName => {
        if (!activeNodeData) return;

        if (actionName === 'add_member' && addMemberProxy) {
          const addParentId = activeNodeData.kind === 'person' ? activeNodeData.personId : activeNodeData.leaderId;
          const addParentName = activeNodeData.kind === 'person' ? activeNodeData.name : activeNodeData.leaderName;
          const addGroupId = activeNodeData.kind === 'person' ? '' : activeNodeData.groupId;
          addMemberProxy.dataset.parentId = addParentId;
          addMemberProxy.dataset.parentName = addParentName;
          addMemberProxy.dataset.groupId = addGroupId;
          clickProxy(addMemberProxy);
          return;
        }

        if (actionName === 'edit_person' && editPersonProxy) {
          const parentGroupItem = activeNode.parentElement ? activeNode.parentElement.closest('.tree-v2-item-group') : null;
          const parentGroupNode = parentGroupItem ? parentGroupItem.firstElementChild : null;
          const currentGroupId = parentGroupNode && parentGroupNode.dataset ? String(parentGroupNode.dataset.groupId || '').trim() : '';
          editPersonProxy.dataset.personId = activeNodeData.personId;
          editPersonProxy.dataset.memberId = activeNodeData.memberId;
          editPersonProxy.dataset.name = activeNodeData.name;
          editPersonProxy.dataset.phone = activeNodeData.phone;
          editPersonProxy.dataset.notes = activeNodeData.notes;
          editPersonProxy.dataset.groupId = currentGroupId;
          editPersonProxy.dataset.isRoot = activeNodeData.isRoot ? '1' : '0';
          clickProxy(editPersonProxy);
          return;
        }

        if (actionName === 'leave_group' && leaveGroupForm) {
          const parentGroupItem = activeNode.parentElement ? activeNode.parentElement.closest('.tree-v2-item-group') : null;
          const parentGroupNode = parentGroupItem ? parentGroupItem.firstElementChild : null;
          const currentGroupId = parentGroupNode && parentGroupNode.dataset ? String(parentGroupNode.dataset.groupId || '').trim() : '';
          if (!currentGroupId) {
            return;
          }
          if (!window.confirm('Keluarkan orang ini dari DG saat ini? Orangnya tetap bisa digabungkan ke DG lain nanti.')) {
            return;
          }
          const idInput = leaveGroupForm.querySelector('input[name="id"]');
          const groupInput = leaveGroupForm.querySelector('input[name="group_id"]');
          if (idInput) idInput.value = activeNodeData.personId || '';
          if (groupInput) groupInput.value = currentGroupId;
          closeActionModal();
          leaveGroupForm.submit();
          return;
        }

        if (actionName === 'delete_person' && deletePersonForm) {
          if (!window.confirm('Hapus data anggota ini dari pohon aktif? Riwayat pemuridan tetap disimpan dan bisa dilihat di Spiritual Journey.')) {
            return;
          }
          const idInput = deletePersonForm.querySelector('input[name="id"]');
          if (idInput) idInput.value = activeNodeData.personId || '';
          closeActionModal();
          deletePersonForm.submit();
          return;
        }

        if (actionName === 'add_group' && addGroupProxy) {
          addGroupProxy.dataset.leaderId = activeNodeData.personId;
          addGroupProxy.dataset.leaderName = activeNodeData.name;
          addGroupProxy.dataset.parentGroupId = '';
          addGroupProxy.dataset.progress = 'DG 1';
          addGroupProxy.dataset.groupTitle = 'Tambah Kelompok';
          clickProxy(addGroupProxy);
          return;
        }

        if (actionName === 'complete_group' && completeGroupForm) {
          if (!window.confirm('Tandai DG ini sebagai selesai? Kelompok akan menjadi tidak aktif dan tidak bisa ditambah anggota lagi.')) {
            return;
          }
          const idInput = completeGroupForm.querySelector('input[name="id"]');
          if (idInput) idInput.value = activeNodeData.groupId || '';
          closeActionModal();
          closeHistoryModal();
          completeGroupForm.submit();
          return;
        }

        if (actionName === 'reactivate_group' && reactivateGroupForm) {
          if (!window.confirm('Aktifkan kembali DG ini? Anggota yang sebelumnya ditutup karena kelompok selesai akan dipulihkan bila belum aktif di kelompok lain.')) {
            return;
          }
          const idInput = reactivateGroupForm.querySelector('input[name="id"]');
          if (idInput) idInput.value = activeNodeData.groupId || '';
          closeActionModal();
          closeHistoryModal();
          reactivateGroupForm.submit();
          return;
        }

        if (actionName === 'upgrade_group' && addGroupProxy) {
          if (String(activeNodeData.status || '').trim().toLowerCase() !== 'active') {
            window.alert('Upgrade DG hanya bisa dilakukan dari kelompok yang masih aktif.');
            return;
          }
          const currentProgress = String(activeNodeData.progress || '').trim();
          let nextProgress = 'DG 2';
          if (currentProgress === 'DG 2') {
            nextProgress = 'DG 3';
          }
          addGroupProxy.dataset.leaderId = activeNodeData.leaderId;
          addGroupProxy.dataset.leaderName = activeNodeData.leaderName;
          addGroupProxy.dataset.parentGroupId = activeNodeData.groupId;
          addGroupProxy.dataset.progress = nextProgress;
          addGroupProxy.dataset.groupTitle = 'Upgrade DG';
          clickProxy(addGroupProxy);
          return;
        }

        if (actionName === 'view_history' && viewHistoryProxy) {
          viewHistoryProxy.setAttribute('data-tree-v2-history-open', activeNodeData.groupId || '');
          closeActionModal(true);
          viewHistoryProxy.click();
        }

      };

      actionButtons.forEach(button => {
        button.addEventListener('click', function () {
          const actionName = button.getAttribute('data-tree-v2-action-do') || '';
          if (actionName !== '') {
            submitAction(actionName);
          }
        });
      });
      personProfileActionButtons.forEach(button => {
        button.addEventListener('click', function () {
          const actionName = button.getAttribute('data-tree-v2-profile-action') || '';
          if (actionName !== '') {
            submitAction(actionName);
          }
        });
      });

      scope.addEventListener('discipleship:tree-mutated', function () {
        personDetailCache.clear();
      });
      scope.addEventListener('discipleship:panel-destroy', function () {
        personDetailCache.clear();
        if (personDetailController) personDetailController.abort();
        personDetailController = null;
      }, { once: true });
    };

    const setupFilePreviewModal = (scope = document) => {
      const filePreviewModal = scope.querySelector('[data-file-preview-modal]');
      if (!filePreviewModal || filePreviewModal.getAttribute('data-file-preview-ready') === '1') {
        return;
      }
      filePreviewModal.setAttribute('data-file-preview-ready', '1');
      const eventScope = scope === document ? document : scope;
      const titleEl = filePreviewModal.querySelector('[data-file-preview-title]');
      const textEl = filePreviewModal.querySelector('[data-file-preview-text]');
      const imageWrapEl = filePreviewModal.querySelector('[data-file-preview-image-wrap]');
      const imageEl = filePreviewModal.querySelector('[data-file-preview-image]');
      const embedWrapEl = filePreviewModal.querySelector('[data-file-preview-embed-wrap]');
      const embedEl = filePreviewModal.querySelector('[data-file-preview-embed]');
      const loadingEl = filePreviewModal.querySelector('[data-file-preview-loading]');
      const noteEl = filePreviewModal.querySelector('[data-file-preview-note]');
      const closeButtons = filePreviewModal.querySelectorAll('[data-file-preview-close]');
      const downloadBtn = filePreviewModal.querySelector('[data-file-preview-download]');
      const setFullResMode = (enabled) => {
        filePreviewModal.classList.toggle('is-fullres', Boolean(enabled));
      };

      const buildDownloadUrl = (fileUrl) => {
        const cleanUrl = String(fileUrl || '').replace(/&amp;/g, '&').trim();
        if (!cleanUrl) return '';
        if (/(^|[?&])download=/.test(cleanUrl)) {
          return cleanUrl;
        }
        return cleanUrl + (cleanUrl.includes('?') ? '&' : '?') + 'download=1';
      };

      const setDownloadButton = (fileUrl, fileName = '') => {
        if (!downloadBtn) return;
        const downloadUrl = buildDownloadUrl(fileUrl);
        if (!downloadUrl) {
          downloadBtn.classList.add('is-hidden');
          downloadBtn.removeAttribute('href');
          downloadBtn.removeAttribute('download');
          return;
        }
        downloadBtn.href = downloadUrl;
        if (String(fileName || '').trim() !== '') {
          downloadBtn.setAttribute('download', String(fileName));
        } else {
          downloadBtn.removeAttribute('download');
        }
        downloadBtn.classList.remove('is-hidden');
      };

      const setBoxText = (el, text) => {
        if (!el) return;
        const value = text || '';
        el.textContent = value;
        if (value) {
          el.classList.remove('is-hidden');
        } else {
          el.classList.add('is-hidden');
        }
      };

      const resetPreviewContent = () => {
        if (textEl) {
          textEl.textContent = '';
          textEl.classList.add('is-hidden');
        }
        if (imageEl) {
          imageEl.src = '';
          imageEl.alt = 'Preview';
        }
        if (imageWrapEl) {
          imageWrapEl.classList.add('is-hidden');
        }
        if (embedEl) {
          embedEl.src = '';
        }
        if (embedWrapEl) {
          embedWrapEl.classList.add('is-hidden');
        }
        setBoxText(loadingEl, '');
        setBoxText(noteEl, '');
        setFullResMode(false);
        setDownloadButton('');
      };

      const openPreviewModal = (trigger) => {
        const type = trigger?.getAttribute('data-file-preview-open') || '';
        const fileTitle = trigger?.dataset?.fileTitle || 'Preview File';
        const fullResMode = trigger?.dataset?.filePreviewFullres === '1';
        filePreviewModal.classList.add('is-open');
        filePreviewModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        resetPreviewContent();
        setFullResMode(fullResMode);

        if (type === 'image') {
          const filePathRaw = trigger?.dataset?.filePath || '';
          const filePath = String(filePathRaw).replace(/&amp;/g, '&');
          if (titleEl) titleEl.textContent = fileTitle ? 'Preview Gambar: ' + fileTitle : 'Preview Gambar';
          if (!filePath) {
            setBoxText(noteEl, 'File gambar tidak ditemukan.');
            return;
          }
          setDownloadButton(filePath, fileTitle);
          if (imageEl) {
            imageEl.src = filePath;
            imageEl.alt = fileTitle || 'Preview Gambar';
          }
          if (imageWrapEl) {
            imageWrapEl.classList.remove('is-hidden');
          }
          return;
        }

        if (type === 'pdf') {
          const filePathRaw = trigger?.dataset?.filePath || '';
          const filePath = String(filePathRaw).replace(/&amp;/g, '&');
          if (titleEl) {
            titleEl.textContent = fileTitle ? 'Preview PDF: ' + fileTitle : 'Preview PDF';
          }
          if (!filePath) {
            setBoxText(noteEl, 'File PDF tidak ditemukan.');
            return;
          }
          setDownloadButton(filePath, fileTitle);
          if (embedEl) {
            embedEl.src = filePath;
          }
          if (embedWrapEl) {
            embedWrapEl.classList.remove('is-hidden');
          }
          return;
        }

        if (titleEl) titleEl.textContent = 'Preview File';
        setBoxText(noteEl, 'Preview tidak tersedia untuk file ini.');
      };

      const closePreviewModal = () => {
        filePreviewModal.classList.remove('is-open');
        filePreviewModal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
        resetPreviewContent();
      };

      eventScope.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-file-preview-open]');
        if (!trigger) return;
        event.preventDefault();
        openPreviewModal(trigger);
      });

      closeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
          closePreviewModal();
        });
      });

      filePreviewModal.addEventListener('click', function (event) {
        if (event.target === filePreviewModal) {
          closePreviewModal();
        }
      });

      eventScope.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && filePreviewModal.classList.contains('is-open')) {
          closePreviewModal();
        }
      });
    };

    const setupDiscipleshipTreeViewport = (scope) => {
      const dragScrollAreas = scope.querySelectorAll('[data-drag-scroll]');
      dragScrollAreas.forEach(area => {
      let isDown = false;
      let startX = 0;
      let startY = 0;
      let startLeft = 0;
      let startTop = 0;
      let moved = false;

      const isInteractive = (target) => {
        return !!target.closest('button, a, input, select, textarea, label, .tree-actions, .group-actions, .modal');
      };

      area.addEventListener('mousedown', (event) => {
        if (event.button !== 0) return;
        if (isInteractive(event.target)) return;
        isDown = true;
        moved = false;
        area.classList.add('is-dragging');
        startX = event.clientX;
        startY = event.clientY;
        startLeft = area.scrollLeft;
        startTop = area.scrollTop;
      });

      window.addEventListener('mousemove', (event) => {
        if (!isDown) return;
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
          moved = true;
        }
        area.scrollLeft = startLeft - dx;
        area.scrollTop = startTop - dy;
      });

      const endDrag = () => {
        if (!isDown) return;
        isDown = false;
        area.classList.remove('is-dragging');
        if (moved) {
          const cancelClick = (e) => {
            e.preventDefault();
            e.stopPropagation();
          };
          area.addEventListener('click', cancelClick, { capture: true, once: true });
        }
      };

      window.addEventListener('mouseup', endDrag);
      area.addEventListener('mouseleave', endDrag);
    });

      const zoomTarget = scope.querySelector('[data-tree-zoom]');
      const zoomControls = scope.querySelector('[data-zoom-controls]');
      const treeScrollArea = scope.querySelector('[data-drag-scroll]');
    const workspaceScope = scope.closest('[data-discipleship-workspace]');
    const selectedBranchKey = String(
      workspaceScope?.getAttribute('data-selected-branch')
      || new URL(window.location.href).searchParams.get('branch_id')
      || 'session'
    );
    const treeViewportStorageKey = 'people_tree_viewport_state:' + encodeURIComponent(selectedBranchKey);
    const hasSessionStorage = (() => {
      try {
        return typeof window.sessionStorage !== 'undefined';
      } catch (error) {
        return false;
      }
    })();
    let currentTreeScale = 0.5;

    const saveTreeViewportState = () => {
      if (!hasSessionStorage || !treeScrollArea) {
        return;
      }
      const payload = {
        scrollLeft: treeScrollArea.scrollLeft || 0,
        scrollTop: treeScrollArea.scrollTop || 0,
        pageScrollY: window.scrollY || window.pageYOffset || 0,
        scale: currentTreeScale,
        savedAt: Date.now(),
      };
      window.sessionStorage.setItem(treeViewportStorageKey, JSON.stringify(payload));
    };

    const restoreTreeViewportState = () => {
      if (!hasSessionStorage || !treeScrollArea) {
        return null;
      }
      const raw = window.sessionStorage.getItem(treeViewportStorageKey);
      if (!raw) {
        return null;
      }
      try {
        return JSON.parse(raw);
      } catch (error) {
        window.sessionStorage.removeItem(treeViewportStorageKey);
        return null;
      }
    };

    if (zoomTarget && zoomControls) {
      const zoomInBtn = zoomControls.querySelector('[data-zoom-in]');
      const zoomOutBtn = zoomControls.querySelector('[data-zoom-out]');
      const zoomValue = zoomControls.querySelector('[data-zoom-value]');
      const supportsZoom = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('zoom', '1');
      const minScale = 0.1;
      const maxScale = 1.5;
      const zoomStep = 0.1;
      let scale = 0.5;

      const applyZoom = () => {
        currentTreeScale = scale;
        const pct = Math.round(scale * 100);
        if (zoomValue) zoomValue.textContent = pct + '%';
        if (supportsZoom) {
          zoomTarget.style.zoom = String(scale);
          zoomTarget.style.transform = '';
        } else {
          zoomTarget.style.zoom = '';
          zoomTarget.style.transform = 'scale(' + scale + ')';
        }
      };

      const clamp = (value) => Math.min(maxScale, Math.max(minScale, value));

      if (zoomInBtn) {
        zoomInBtn.addEventListener('click', () => {
          scale = clamp(Number((scale + zoomStep).toFixed(3)));
          applyZoom();
          saveTreeViewportState();
        });
      }

      if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', () => {
          scale = clamp(Number((scale - zoomStep).toFixed(3)));
          applyZoom();
          saveTreeViewportState();
        });
      }

      const savedViewport = restoreTreeViewportState();
      if (savedViewport && typeof savedViewport.scale === 'number' && Number.isFinite(savedViewport.scale)) {
        scale = clamp(savedViewport.scale);
      }
      applyZoom();
    }

      const treeSearchInput = scope.querySelector('[data-tree-search-input]');
      const treeSearchSubmit = scope.querySelector('[data-tree-search-submit]');
    if (treeScrollArea && treeSearchInput && treeSearchSubmit) {
      let searchHighlightTimer = null;

      const clearSearchHighlight = () => {
        scope.querySelectorAll('.tree-v2-node.is-search-hit').forEach((node) => {
          node.classList.remove('is-search-hit');
        });
      };

      const normalizeSearchText = (value) => String(value || '').trim().toLowerCase();

      const findSearchNode = (query) => {
        const normalizedQuery = normalizeSearchText(query);
        if (!normalizedQuery) {
          return null;
        }
        const nodeEls = Array.from(scope.querySelectorAll('.tree-v2-node[data-search-name]'));
        let startsWithMatch = null;
        let includesMatch = null;
        for (const nodeEl of nodeEls) {
          const searchName = normalizeSearchText(nodeEl.getAttribute('data-search-name'));
          if (!searchName) {
            continue;
          }
          if (searchName === normalizedQuery) {
            return nodeEl;
          }
          if (!startsWithMatch && searchName.startsWith(normalizedQuery)) {
            startsWithMatch = nodeEl;
          }
          if (!includesMatch && searchName.includes(normalizedQuery)) {
            includesMatch = nodeEl;
          }
        }
        return startsWithMatch || includesMatch;
      };

      const scrollToSearchNode = (nodeEl) => {
        const areaRect = treeScrollArea.getBoundingClientRect();
        const nodeRect = nodeEl.getBoundingClientRect();
        const nextLeft = treeScrollArea.scrollLeft + (nodeRect.left - areaRect.left) - ((areaRect.width - nodeRect.width) / 2);
        const nextTop = treeScrollArea.scrollTop + (nodeRect.top - areaRect.top) - ((areaRect.height - nodeRect.height) / 2);
        treeScrollArea.scrollTo({
          left: Math.max(0, nextLeft),
          top: Math.max(0, nextTop),
          behavior: 'smooth',
        });
        clearSearchHighlight();
        nodeEl.classList.add('is-search-hit');
        if (searchHighlightTimer) {
          window.clearTimeout(searchHighlightTimer);
        }
        searchHighlightTimer = window.setTimeout(() => {
          nodeEl.classList.remove('is-search-hit');
        }, 2200);
        if (typeof nodeEl.focus === 'function') {
          nodeEl.focus({ preventScroll: true });
        }
      };

      const runTreeSearch = () => {
        const nodeEl = findSearchNode(treeSearchInput.value);
        if (!nodeEl) {
          window.alert('Nama tidak ditemukan di pohon pemuridan.');
          return;
        }
        scrollToSearchNode(nodeEl);
      };

      treeSearchSubmit.addEventListener('click', runTreeSearch);
      treeSearchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
          return;
        }
        event.preventDefault();
        runTreeSearch();
      });
    }

    if (treeScrollArea) {
      const restoredViewport = restoreTreeViewportState();
      if (restoredViewport) {
        window.requestAnimationFrame(() => {
          treeScrollArea.scrollLeft = Math.max(0, Number(restoredViewport.scrollLeft || 0));
          treeScrollArea.scrollTop = Math.max(0, Number(restoredViewport.scrollTop || 0));
          const pageScrollY = Math.max(0, Number(restoredViewport.pageScrollY || 0));
          window.scrollTo(0, pageScrollY);
          window.requestAnimationFrame(() => {
            treeScrollArea.scrollLeft = Math.max(0, Number(restoredViewport.scrollLeft || 0));
            treeScrollArea.scrollTop = Math.max(0, Number(restoredViewport.scrollTop || 0));
            window.scrollTo(0, pageScrollY);
          });
        });
        window.sessionStorage.removeItem(treeViewportStorageKey);
      }

      treeScrollArea.addEventListener('scroll', () => {
        saveTreeViewportState();
      }, { passive: true });

      scope.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
          saveTreeViewportState();
        });
      });

      window.addEventListener('beforeunload', () => {
        saveTreeViewportState();
      });
      }
    };

    const initDiscipleshipTreePane = (panel) => {
      if (
        !panel
        || panel.getAttribute('data-tab-key') !== 'tree'
        || panel.getAttribute('data-discipleship-tree-ready') === '1'
      ) {
        return;
      }

      panel.setAttribute('data-discipleship-tree-ready', '1');
      setupDiscipleshipTreeHistory(panel);
      setupDiscipleshipTreePeopleModal(panel);
      setupDiscipleshipTreeGroupModal(panel);
      setupDiscipleshipTreeActions(panel);
      setupFilePreviewModal(panel);
      setupDiscipleshipTreeViewport(panel);
    };

    const setupMemberFeedbackRecap = (scope = document) => {
      const panel = scope && scope.matches && scope.matches('[data-member-feedback-recap-panel]')
        ? scope
        : (scope && scope.querySelector ? scope.querySelector('[data-member-feedback-recap-panel]') : null);
      if (!panel || panel.getAttribute('data-member-feedback-recap-ready') === '1') {
        return;
      }
      panel.setAttribute('data-member-feedback-recap-ready', '1');

      const groupModal = panel.querySelector('[data-member-feedback-group-modal]');
      const detailModal = panel.querySelector('[data-member-feedback-detail-modal]');
      const openModal = (modal) => {
        if (!modal) {
          return;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };
      const closeModal = (modal) => {
        if (!modal) {
          return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
      };
      const templateByAttribute = (attribute, value) => {
        const key = String(value || '').trim();
        if (!key) {
          return null;
        }

        return panel.querySelector('template[' + attribute + '="' + cssAttributeValue(key) + '"]');
      };

      if (groupModal) {
        const titleEl = groupModal.querySelector('[data-member-feedback-group-title]');
        const bodyEl = groupModal.querySelector('[data-member-feedback-group-body]');
        const closeButtons = groupModal.querySelectorAll('[data-member-feedback-group-close]');
        const openGroup = (groupSessionId) => {
          const templateEl = templateByAttribute('data-member-feedback-group-template', groupSessionId);
          if (!templateEl) {
            return;
          }
          if (titleEl) {
            titleEl.textContent = templateEl.getAttribute('data-member-feedback-group-template-title') || 'Feedback Kelompok';
          }
          if (bodyEl) {
            bodyEl.innerHTML = templateEl.innerHTML;
          }
          openModal(groupModal);
        };

        panel.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-member-feedback-group-open]');
          if (!trigger || !panel.contains(trigger)) {
            return;
          }
          event.preventDefault();
          openGroup(trigger.getAttribute('data-member-feedback-group-open') || '');
        });
        closeButtons.forEach((button) => {
          button.addEventListener('click', () => closeModal(groupModal));
        });
        groupModal.addEventListener('click', (event) => {
          if (event.target === groupModal) {
            closeModal(groupModal);
          }
        });
      }

      if (detailModal) {
        const titleEl = detailModal.querySelector('[data-member-feedback-detail-title]');
        const bodyEl = detailModal.querySelector('[data-member-feedback-detail-body]');
        const closeButtons = detailModal.querySelectorAll('[data-member-feedback-detail-close]');
        const openDetail = (feedbackId) => {
          const templateEl = templateByAttribute('data-member-feedback-detail-template', feedbackId);
          if (!templateEl) {
            return;
          }
          if (titleEl) {
            titleEl.textContent = templateEl.getAttribute('data-member-feedback-detail-template-title') || 'Detail Feedback';
          }
          if (bodyEl) {
            bodyEl.innerHTML = templateEl.innerHTML;
          }
          openModal(detailModal);
        };

        panel.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-member-feedback-detail-open]');
          if (!trigger || !panel.contains(trigger)) {
            return;
          }
          event.preventDefault();
          openDetail(trigger.getAttribute('data-member-feedback-detail-open') || '');
        });
        closeButtons.forEach((button) => {
          button.addEventListener('click', () => closeModal(detailModal));
        });
        detailModal.addEventListener('click', (event) => {
          if (event.target === detailModal) {
            closeModal(detailModal);
          }
        });
      }

      panel.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
          return;
        }
        if (detailModal && detailModal.classList.contains('is-open')) {
          closeModal(detailModal);
          return;
        }
        if (groupModal && groupModal.classList.contains('is-open')) {
          closeModal(groupModal);
        }
      });
    };

    const setupMskPanelInteractions = (scope = document) => {
      const panel = scope && scope.matches && scope.matches('[data-tab-key="msk"]')
        ? scope
        : (scope && scope.querySelector ? scope.querySelector('[data-tab-key="msk"]') : null);
      if (!panel || panel.getAttribute('data-msk-panel-ready') === '1') {
        return;
      }
      panel.setAttribute('data-msk-panel-ready', '1');

      const detailUrlTemplate = panel.getAttribute('data-msk-detail-url-template') || '';
      const detailCache = new Map();
      let activeDetailController = null;
      let openEditById = null;
      const escapeDetailId = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      })[character]);
      const detailUrl = (participantId, mode) => {
        const key = String(participantId || '').trim();
        if (!key || !detailUrlTemplate) {
          return '';
        }
        const url = new URL(detailUrlTemplate.replace('__id__', encodeURIComponent(key)), window.location.origin);
        if (mode === 'edit') {
          url.searchParams.set('mode', 'edit');
        }
        const branchInput = panel.querySelector('input[name="branch_id"]');
        const batchInput = panel.querySelector('[data-msk-batch-input], input[name="batch_month"]');
        if (branchInput && String(branchInput.value || '').trim()) {
          url.searchParams.set('branch_id', String(branchInput.value).trim());
        }
        if (batchInput && String(batchInput.value || '').trim()) {
          url.searchParams.set('batch_month', String(batchInput.value).trim());
        }

        return url.toString();
      };
      const fetchDetail = async (participantId, mode) => {
        const cacheKey = mode + ':' + String(participantId || '').trim();
        if (detailCache.has(cacheKey)) {
          return detailCache.get(cacheKey);
        }
        const url = detailUrl(participantId, mode);
        if (!url) {
          throw new Error('detail-url-unavailable');
        }
        if (activeDetailController) {
          activeDetailController.abort();
        }
        const controller = new AbortController();
        activeDetailController = controller;
        const response = await window.fetch(url, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          signal: controller.signal
        });
        if (!response.ok) {
          throw new Error('detail-request-failed');
        }
        const data = await response.json();
        detailCache.set(cacheKey, data);
        if (activeDetailController === controller) {
          activeDetailController = null;
        }

        return data;
      };
      const openModal = (modal) => {
        if (!modal) {
          return;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };
      const closeModal = (modal) => {
        if (!modal) {
          return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
      };
      const clearSelectionAlerts = () => {
        panel.querySelectorAll('.alert.danger').forEach((alertEl) => {
          const message = String(alertEl.textContent || '').trim();
          if (
            message === 'Data peserta kelas MSK yang ingin diedit tidak ditemukan.' ||
            message === 'Data peserta kelas MSK yang ingin dilihat tidak ditemukan.'
          ) {
            alertEl.remove();
          }
        });
      };

      const viewModal = panel.querySelector('[data-msk-view-modal]');
      if (viewModal) {
        const titleEl = viewModal.querySelector('[data-msk-view-title]');
        const bodyEl = viewModal.querySelector('[data-msk-view-body]');
        const editLinkEl = viewModal.querySelector('[data-msk-view-edit-link]');
        const openView = async (participantId) => {
          const key = String(participantId || '').trim();
          if (!key) { return; }
          if (titleEl) { titleEl.textContent = 'Detail Peserta MSK'; }
          if (bodyEl) { bodyEl.innerHTML = '<div class="panel-note">Memuat detail peserta...</div>'; }
          if (editLinkEl) { editLinkEl.classList.add('is-hidden'); }
          openModal(viewModal);
          try {
            const data = await fetchDetail(key, 'view');
            if (titleEl) { titleEl.textContent = data.title || 'Detail Peserta MSK'; }
            if (bodyEl) { bodyEl.innerHTML = String(data.html || ''); }
            if (editLinkEl && data.edit_url) {
              editLinkEl.setAttribute('href', data.edit_url);
              editLinkEl.setAttribute('data-msk-edit-from-view', key);
              editLinkEl.classList.remove('is-hidden');
            }
          } catch (error) {
            if (error && error.name === 'AbortError') { return; }
            if (bodyEl) {
              bodyEl.innerHTML = '<div class="panel-note">Detail gagal dimuat. <button class="btn tiny secondary" type="button" data-msk-detail-retry="view" data-participant-id="' + escapeDetailId(key) + '">Coba lagi</button></div>';
            }
          }
        };

        panel.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-msk-view-open]');
          if (!trigger || !panel.contains(trigger)) {
            return;
          }
          event.preventDefault();
          openView(trigger.getAttribute('data-msk-view-open') || '');
        });
        panel.addEventListener('click', (event) => {
          const retry = event.target.closest('[data-msk-detail-retry="view"]');
          if (retry) {
            event.preventDefault();
            openView(retry.getAttribute('data-participant-id') || '');
          }
          const editFromView = event.target.closest('[data-msk-edit-from-view]');
          if (editFromView && openEditById) {
            event.preventDefault();
            closeModal(viewModal);
            openEditById(editFromView.getAttribute('data-msk-edit-from-view') || '');
          }
        });
        viewModal.addEventListener('click', (event) => {
          if (event.target === viewModal || event.target.closest('[data-msk-view-close]')) {
            event.preventDefault();
            closeModal(viewModal);
          }
        });
        const autoOpenId = viewModal.getAttribute('data-msk-view-auto-open') || '';
        if (autoOpenId) {
          openView(autoOpenId);
        }
      }

      const editModal = panel.querySelector('[data-msk-edit-modal]');
      if (editModal) {
        const titleEl = editModal.querySelector('[data-msk-edit-title]');
        const bodyEl = editModal.querySelector('[data-msk-edit-body]');
        const openEdit = async (participantId) => {
          const key = String(participantId || '').trim();
          if (!key) { return; }
          if (titleEl) { titleEl.textContent = 'Edit Peserta MSK'; }
          if (bodyEl) { bodyEl.innerHTML = '<div class="panel-note">Memuat form edit...</div>'; }
          openModal(editModal);
          clearSelectionAlerts();
          try {
            const data = await fetchDetail(key, 'edit');
            if (titleEl) { titleEl.textContent = data.title || 'Edit Peserta MSK'; }
            if (bodyEl) {
              bodyEl.innerHTML = String(data.html || '');
              initMskForms(bodyEl);
              const firstInput = bodyEl.querySelector('input, select, textarea');
              if (firstInput && typeof firstInput.focus === 'function') { firstInput.focus(); }
            }
          } catch (error) {
            if (error && error.name === 'AbortError') { return; }
            if (bodyEl) {
              bodyEl.innerHTML = '<div class="panel-note">Form edit gagal dimuat. <button class="btn tiny secondary" type="button" data-msk-detail-retry="edit" data-participant-id="' + escapeDetailId(key) + '">Coba lagi</button></div>';
            }
          }
        };
        openEditById = openEdit;

        panel.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-msk-edit-open]');
          if (!trigger || !panel.contains(trigger)) {
            return;
          }
          event.preventDefault();
          openEdit(trigger.getAttribute('data-msk-edit-open') || '');
        });
        panel.addEventListener('click', (event) => {
          const retry = event.target.closest('[data-msk-detail-retry="edit"]');
          if (retry) {
            event.preventDefault();
            openEdit(retry.getAttribute('data-participant-id') || '');
          }
        });
        editModal.addEventListener('click', (event) => {
          if (event.target === editModal || event.target.closest('[data-msk-edit-close]')) {
            event.preventDefault();
            closeModal(editModal);
          }
        });
        const autoOpenId = editModal.getAttribute('data-msk-edit-auto-open') || '';
        if (autoOpenId) {
          openEdit(autoOpenId);
        }
      }

      const createModal = panel.querySelector('[data-msk-create-modal]');
      if (createModal) {
        const openCreate = () => {
          openModal(createModal);
          initMskForms(createModal);
          const firstInput = createModal.querySelector('input, select, textarea');
          if (firstInput && typeof firstInput.focus === 'function') {
            firstInput.focus();
          }
        };

        panel.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-msk-create-open]');
          if (!trigger || !panel.contains(trigger)) {
            return;
          }
          event.preventDefault();
          openCreate();
        });
        createModal.addEventListener('click', (event) => {
          if (event.target === createModal || event.target.closest('[data-msk-create-close]')) {
            event.preventDefault();
            closeModal(createModal);
          }
        });
      }

      panel.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
          return;
        }
        [createModal, editModal, viewModal].forEach((modal) => {
          if (modal && modal.classList.contains('is-open')) {
            closeModal(modal);
          }
        });
      });
      panel.addEventListener('discipleship:panel-destroy', () => {
        if (activeDetailController) {
          activeDetailController.abort();
          activeDetailController = null;
        }
        detailCache.clear();
      }, { once: true });
    };

    const setupSpiritualJourneyPanelInteractions = (scope = document) => {
      const panel = scope && scope.matches && scope.matches('[data-tab-key="spiritual"]')
        ? scope
        : (scope && scope.querySelector ? scope.querySelector('[data-tab-key="spiritual"]') : null);
      if (!panel || panel.getAttribute('data-spiritual-journey-panel-ready') === '1') {
        return;
      }
      panel.setAttribute('data-spiritual-journey-panel-ready', '1');

      const modal = panel.querySelector('[data-spiritual-journey-view-modal]');
      if (!modal) {
        return;
      }
      const titleEl = modal.querySelector('[data-spiritual-journey-view-title]');
      const bodyEl = modal.querySelector('[data-spiritual-journey-view-body]');
      const detailUrlTemplate = panel.getAttribute('data-spiritual-detail-url-template') || '';
      const detailCache = new Map();
      let activeController = null;
      const escapeDetailId = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      })[character]);
      const detailUrl = (personKey) => {
        const key = String(personKey || '').trim();
        if (!key || !detailUrlTemplate) { return ''; }
        const url = new URL(detailUrlTemplate.replace('__id__', encodeURIComponent(key)), window.location.origin);
        const branchInput = panel.querySelector('input[name="branch_id"]');
        if (branchInput && String(branchInput.value || '').trim()) {
          url.searchParams.set('branch_id', String(branchInput.value).trim());
        }

        return url.toString();
      };
      const openView = async (personKey) => {
        const key = String(personKey || '').trim();
        if (!key) { return; }
        if (titleEl) { titleEl.textContent = 'Profil Spiritual'; }
        if (bodyEl) { bodyEl.innerHTML = '<div class="panel-note">Memuat profil peserta...</div>'; }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        try {
          let data = detailCache.get(key);
          if (!data) {
            if (activeController) { activeController.abort(); }
            const controller = new AbortController();
            activeController = controller;
            const response = await window.fetch(detailUrl(key), {
              credentials: 'same-origin',
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              signal: controller.signal
            });
            if (!response.ok) { throw new Error('detail-request-failed'); }
            data = await response.json();
            detailCache.set(key, data);
            if (activeController === controller) { activeController = null; }
          }
          if (titleEl) { titleEl.textContent = data.title || 'Profil Spiritual'; }
          if (bodyEl) { bodyEl.innerHTML = String(data.html || ''); }
        } catch (error) {
          if (error && error.name === 'AbortError') { return; }
          if (bodyEl) {
            bodyEl.innerHTML = '<div class="panel-note">Profil gagal dimuat. <button class="btn tiny secondary" type="button" data-spiritual-detail-retry data-participant-id="' + escapeDetailId(key) + '">Coba lagi</button></div>';
          }
        }
      };
      const closeView = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
      };

      panel.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-spiritual-journey-view-open]');
        if (!trigger || !panel.contains(trigger)) {
          return;
        }
        event.preventDefault();
        openView(trigger.getAttribute('data-spiritual-journey-view-open') || '');
      });
      panel.addEventListener('click', (event) => {
        const retry = event.target.closest('[data-spiritual-detail-retry]');
        if (retry) {
          event.preventDefault();
          openView(retry.getAttribute('data-participant-id') || '');
        }
      });
      modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-spiritual-journey-view-close]')) {
          event.preventDefault();
          closeView();
        }
      });
      panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          closeView();
        }
      });
      panel.addEventListener('discipleship:panel-destroy', () => {
        if (activeController) {
          activeController.abort();
          activeController = null;
        }
        detailCache.clear();
      }, { once: true });
    };

    const executePanelScripts = (panel) => {
      if (!panel || !panel.querySelectorAll) {
        return;
      }
      panel.querySelectorAll('script').forEach((scriptEl) => {
        const executableScript = document.createElement('script');
        Array.from(scriptEl.attributes).forEach((attribute) => {
          executableScript.setAttribute(attribute.name, attribute.value);
        });
        executableScript.textContent = scriptEl.textContent || '';
        scriptEl.replaceWith(executableScript);
      });
    };

    const setupDiscipleshipWorkspace = () => {
      const workspace = document.querySelector('[data-discipleship-workspace]');
      if (!workspace || workspace.getAttribute('data-discipleship-workspace-ready') === '1') {
        return;
      }

      const panelsHost = workspace.querySelector('[data-discipleship-panels]');
      const tabList = workspace.querySelector('[data-discipleship-tabs]');
      const refreshButton = workspace.querySelector('[data-discipleship-tab-refresh]');
      const tabEls = Array.from(workspace.querySelectorAll('[data-discipleship-tab]'));
      const initialPanels = Array.from(workspace.querySelectorAll('[data-discipleship-tab-panel]'));
      if (!panelsHost || !tabList || tabEls.length === 0 || initialPanels.length !== 1) {
        return;
      }

      const tabs = new Map();
      tabEls.forEach((tabEl) => {
        const key = String(tabEl.getAttribute('data-tab-key') || '').trim();
        if (key) {
          tabs.set(key, tabEl);
        }
      });
      if (tabs.size === 0) {
        return;
      }

      const initialPanel = initialPanels[0];
      const initialKey = String(initialPanel.getAttribute('data-tab-key') || '').trim();
      if (!initialKey || !tabs.has(initialKey)) {
        return;
      }

      workspace.setAttribute('data-discipleship-workspace-ready', '1');

      const panels = new Map([[initialKey, initialPanel]]);
      const panelUrls = new Map([[initialKey, window.location.href]]);
      const bodyClassByTab = {
        dashboard: 'page-discipleship-dashboard',
        tree: 'page-tree-v2',
        people: 'page-discipleship-people-list',
        groups: 'page-discipleship-groups-list'
      };
      tabEls.forEach((tabEl) => {
        const key = String(tabEl.getAttribute('data-tab-key') || '').trim();
        const bodyClass = String(tabEl.getAttribute('data-body-class') || '').trim();
        if (key && bodyClass) {
          bodyClassByTab[key] = bodyClass;
        }
      });
      if (initialKey) {
        const initialBodyClass = String(initialPanel.getAttribute('data-body-class') || '').trim();
        if (initialBodyClass) {
          bodyClassByTab[initialKey] = initialBodyClass;
        }
      }
      const managedBodyClasses = new Set();
      const addManagedBodyClasses = (classNames) => {
        String(classNames || '').split(/\s+/).forEach((className) => {
          if (className) {
            managedBodyClasses.add(className);
          }
        });
      };
      Object.values(bodyClassByTab).forEach(addManagedBodyClasses);
      let currentKey = initialKey;
      let activeController = null;
      let requestSequence = 0;
      let retryTarget = null;

      const statusEl = document.createElement('div');
      statusEl.className = 'discipleship-workspace__status';
      statusEl.setAttribute('role', 'status');
      statusEl.setAttribute('aria-live', 'polite');
      statusEl.hidden = true;

      const loadingEl = document.createElement('span');
      loadingEl.textContent = 'Memuat tampilan...';
      statusEl.appendChild(loadingEl);

      const retryButton = document.createElement('button');
      retryButton.className = 'btn tiny ghost';
      retryButton.type = 'button';
      retryButton.textContent = 'Coba lagi';
      retryButton.hidden = true;
      statusEl.appendChild(retryButton);
      panelsHost.appendChild(statusEl);

      const initializePanel = (panel) => {
        const key = String(panel.getAttribute('data-tab-key') || '').trim();
        if (key) {
          if (!panel.id) {
            panel.id = 'discipleship-tabpanel-' + key;
          }
          const panelBodyClass = String(panel.getAttribute('data-body-class') || '').trim();
          if (panelBodyClass) {
            bodyClassByTab[key] = panelBodyClass;
            addManagedBodyClasses(panelBodyClass);
          }
          panel.setAttribute('role', 'tabpanel');
          panel.setAttribute('aria-labelledby', 'discipleship-tab-' + key);
          panel.setAttribute('tabindex', '0');
        }
        if (panel.getAttribute('data-discipleship-panel-lifecycle-ready') !== '1') {
          panel.setAttribute('data-discipleship-panel-lifecycle-ready', '1');
          panel.addEventListener('discipleship:panel-deactivate', () => {
            panel.querySelectorAll('.modal.is-open').forEach((modalEl) => {
              modalEl.classList.remove('is-open');
              modalEl.setAttribute('aria-hidden', 'true');
            });
            syncBodyModalState();
          });
        }
        setupDiscipleshipDashboard(panel);
        setupHorizontalTableScroll(panel);
        setupDiscipleshipPeopleList(panel);
        setupDiscipleshipGroupsList(panel);
        setupSpiritualJourneyList(panel);
        setupMskList(panel);
        setupSpiritualJourneyPanelInteractions(panel);
        setupMskPanelInteractions(panel);
        setupFilterControls(panel);
        setupAutoSubmitSearchForms(panel);
        setupMemberFeedbackRecap(panel);
        initDiscipleshipTreePane(panel);
      };

      const canonicalUrlForTab = (key) => {
        const tabEl = tabs.get(key);
        return tabEl ? new URL(tabEl.href, window.location.href).toString() : '';
      };

      const comparableUrl = (url) => {
        try {
          const parsed = new URL(url, window.location.href);
          parsed.hash = '';
          parsed.searchParams.sort();
          return parsed.toString();
        } catch (_error) {
          return String(url || '');
        }
      };

      const setBusy = (isBusy) => {
        workspace.classList.toggle('is-loading', isBusy);
        panelsHost.classList.toggle('is-loading', isBusy);
        workspace.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        panelsHost.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        if (refreshButton) {
          refreshButton.disabled = isBusy;
          refreshButton.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
        }
        if (isBusy) {
          statusEl.classList.add('discipleship-workspace__loading');
          statusEl.classList.remove('discipleship-workspace__retry');
          loadingEl.textContent = 'Memuat tampilan...';
          retryButton.hidden = true;
          statusEl.hidden = false;
        } else {
          statusEl.classList.remove('discipleship-workspace__loading');
          if (!retryTarget) {
            statusEl.hidden = true;
          }
        }
      };

      const showRetry = (key, url, shouldPush, forceReload) => {
        retryTarget = { key, url, shouldPush, forceReload };
        workspace.classList.remove('is-loading');
        panelsHost.classList.remove('is-loading');
        workspace.setAttribute('aria-busy', 'false');
        panelsHost.setAttribute('aria-busy', 'false');
        statusEl.classList.remove('discipleship-workspace__loading');
        statusEl.classList.add('discipleship-workspace__retry');
        loadingEl.textContent = 'Tampilan gagal dimuat.';
        retryButton.hidden = false;
        statusEl.hidden = false;
      };

      const clearRetry = () => {
        retryTarget = null;
        statusEl.classList.remove('discipleship-workspace__retry');
        retryButton.hidden = true;
        if (workspace.getAttribute('aria-busy') !== 'true') {
          statusEl.hidden = true;
        }
      };

      const cancelRequest = () => {
        requestSequence += 1;
        if (activeController) {
          activeController.abort();
          activeController = null;
        }
      };

      const replaceLegacyBodyClass = (key) => {
        managedBodyClasses.forEach((className) => {
          document.body.classList.remove(className);
        });
        const nextClass = String(bodyClassByTab[key] || '').trim();
        if (nextClass) {
          nextClass.split(/\s+/).forEach((className) => {
            if (className) {
              document.body.classList.add(className);
            }
          });
        }
      };

      const updateTabs = (key) => {
        tabs.forEach((tabEl, tabKey) => {
          const isActive = tabKey === key;
          tabEl.classList.toggle('is-active', isActive);
          tabEl.setAttribute('aria-selected', isActive ? 'true' : 'false');
          tabEl.setAttribute('tabindex', isActive ? '0' : '-1');
          if (isActive) {
            tabEl.setAttribute('aria-current', 'page');
          } else {
            tabEl.removeAttribute('aria-current');
          }
        });
      };

      const updateBranchLinks = (url) => {
        let activeUrl;
        try {
          activeUrl = new URL(url || window.location.href, window.location.href);
        } catch (_error) {
          return;
        }

        const discardedParams = [
          'page',
          'per_page',
          'cursor',
          'limit',
          'rekap_cabang',
          'edit',
          'view',
          'error',
          'conflict',
          'left_group',
          'person_archived',
          'group_completed',
          'group_reactivated',
          'edit_msk_sessions',
          'msk_session_saved',
          'converted',
          'imported',
          'import_msk_inserted',
          'import_msk_updated',
          'import_msk_unchanged',
          'import_error_count',
          'import_error_preview'
        ];
        discardedParams.forEach((param) => activeUrl.searchParams.delete(param));

        document.querySelectorAll('[data-discipleship-branch-option]').forEach((branchLink) => {
          let branchUrl;
          try {
            branchUrl = new URL(branchLink.href, window.location.href);
          } catch (_error) {
            return;
          }
          const branchId = branchUrl.searchParams.get('branch_id');
          const nextUrl = new URL(activeUrl.toString());
          if (branchId !== null && branchId !== '') {
            nextUrl.searchParams.set('branch_id', branchId);
          } else {
            nextUrl.searchParams.delete('branch_id');
          }
          branchLink.href = nextUrl.toString();
        });
      };

      const setHistoryUrl = (url, shouldPush, key) => {
        if (!url || !window.history) {
          return;
        }
        const state = Object.assign({}, window.history.state || {}, {
          discipleshipTab: key
        });
        if (shouldPush && typeof window.history.pushState === 'function') {
          window.history.pushState(state, '', url);
        }
      };

      const showPanel = (key, url, shouldPush) => {
        const nextPanel = panels.get(key);
        if (!nextPanel) {
          return;
        }

        panels.forEach((panel, panelKey) => {
          const isActive = panelKey === key;
          const wasActive = !panel.hidden;
          panel.hidden = !isActive;
          panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
          if (!isActive && wasActive) {
            panel.dispatchEvent(new CustomEvent('discipleship:panel-deactivate', {
              detail: { tabKey: panelKey }
            }));
          }
        });

        currentKey = key;
        panelUrls.set(key, url);
        workspace.setAttribute('data-active-tab', key);
        updateTabs(key);
        updateBranchLinks(url);
        replaceLegacyBodyClass(key);

        const pageTitle = String(nextPanel.getAttribute('data-page-title') || '').trim();
        if (pageTitle) {
          document.title = pageTitle;
        }

        setHistoryUrl(url, shouldPush, key);
        nextPanel.dispatchEvent(new CustomEvent('discipleship:panel-activate', {
          detail: { tabKey: key }
        }));
        scheduleViewportTableHeights();
        window.setTimeout(scheduleViewportTableHeights, 80);
      };

      const hardNavigate = (url) => {
        window.location.assign(url || window.location.href);
      };

      const parsePanel = (html, expectedKey) => {
        const parsed = new DOMParser().parseFromString(String(html || ''), 'text/html');
        const markedPanels = parsed.querySelectorAll('[data-discipleship-tab-panel]');
        if (markedPanels.length !== 1) {
          return null;
        }
        const panel = markedPanels[0];
        if (String(panel.getAttribute('data-tab-key') || '').trim() !== expectedKey) {
          return null;
        }
        return panel;
      };

      const loadTab = async (key, url, shouldPush, forceReload = false) => {
        cancelRequest();
        clearRetry();

        if (panels.has(key) && !forceReload) {
          setBusy(false);
          showPanel(key, url, shouldPush);
          return;
        }

        const controller = new AbortController();
        activeController = controller;
        const sequence = requestSequence;
        setBusy(true);

        let response;
        try {
          response = await window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
              'X-Discipleship-Fragment': 'tab',
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'text/html'
            },
            signal: controller.signal
          });
        } catch (error) {
          if (controller.signal.aborted || sequence !== requestSequence) {
            return;
          }
          activeController = null;
          setBusy(false);
          showRetry(key, url, shouldPush, forceReload);
          return;
        }

        if (controller.signal.aborted || sequence !== requestSequence) {
          return;
        }

        if (response.redirected) {
          if (activeController === controller) {
            activeController = null;
          }
          setBusy(false);
          hardNavigate(response.url || url);
          return;
        }

        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        if (!response.ok || !contentType.includes('text/html')) {
          if (activeController === controller) {
            activeController = null;
          }
          setBusy(false);
          hardNavigate(url);
          return;
        }

        let html;
        try {
          html = await response.text();
        } catch (error) {
          if (controller.signal.aborted || sequence !== requestSequence) {
            return;
          }
          if (activeController === controller) {
            activeController = null;
          }
          setBusy(false);
          showRetry(key, url, shouldPush, forceReload);
          return;
        }
        if (controller.signal.aborted || sequence !== requestSequence) {
          return;
        }
        if (activeController === controller) {
          activeController = null;
        }

        const panel = parsePanel(html, key);
        if (!panel) {
          setBusy(false);
          hardNavigate(url);
          return;
        }

        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
        const stalePanel = panels.get(key);
        if (stalePanel) {
          stalePanel.hidden = true;
          stalePanel.setAttribute('aria-hidden', 'true');
          stalePanel.dispatchEvent(new CustomEvent('discipleship:panel-destroy', {
            detail: { tabKey: key }
          }));
          stalePanel.replaceWith(panel);
        } else {
          panelsHost.appendChild(panel);
        }
        executePanelScripts(panel);
        panels.set(key, panel);
        panelUrls.set(key, url);
        initializePanel(panel);
        setBusy(false);
        showPanel(key, url, shouldPush);
      };

      const activateTab = (key, options = {}) => {
        if (!tabs.has(key)) {
          return;
        }
        const targetUrl = String(
          options.url
          || panelUrls.get(key)
          || canonicalUrlForTab(key)
        );
        if (!targetUrl) {
          return;
        }
        loadTab(key, targetUrl, options.push === true, options.forceReload === true);
      };

      tabList.addEventListener('click', (event) => {
        const tabEl = event.target.closest('[data-discipleship-tab]');
        if (
          !tabEl
          || event.defaultPrevented
          || event.button !== 0
          || event.metaKey
          || event.ctrlKey
          || event.shiftKey
          || event.altKey
        ) {
          return;
        }

        const key = String(tabEl.getAttribute('data-tab-key') || '').trim();
        if (!tabs.has(key)) {
          return;
        }
        event.preventDefault();
        const targetUrl = panelUrls.get(key) || new URL(tabEl.href, window.location.href).toString();
        activateTab(key, { url: targetUrl, push: key !== currentKey });
      });

      tabList.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
          return;
        }

        const currentTab = event.target.closest('[data-discipleship-tab]');
        const currentIndex = tabEls.indexOf(currentTab);
        if (currentIndex < 0) {
          return;
        }

        event.preventDefault();
        let nextIndex = currentIndex;
        if (event.key === 'Home') {
          nextIndex = 0;
        } else if (event.key === 'End') {
          nextIndex = tabEls.length - 1;
        } else if (event.key === 'ArrowRight') {
          nextIndex = (currentIndex + 1) % tabEls.length;
        } else {
          nextIndex = (currentIndex - 1 + tabEls.length) % tabEls.length;
        }

        const nextTab = tabEls[nextIndex];
        const nextKey = String(nextTab.getAttribute('data-tab-key') || '').trim();
        nextTab.focus();
        activateTab(nextKey, {
          url: panelUrls.get(nextKey) || new URL(nextTab.href, window.location.href).toString(),
          push: nextKey !== currentKey
        });
      });

      retryButton.addEventListener('click', () => {
        if (!retryTarget) {
          return;
        }
        const target = retryTarget;
        activateTab(target.key, {
          url: target.url,
          push: target.shouldPush === true,
          forceReload: target.forceReload === true
        });
      });

      if (refreshButton) {
        refreshButton.addEventListener('click', () => {
          const targetUrl = panelUrls.get(currentKey) || canonicalUrlForTab(currentKey) || window.location.href;
          activateTab(currentKey, {
            url: targetUrl,
            push: false,
            forceReload: true
          });
        });
      }

      window.addEventListener('popstate', () => {
        const currentUrl = new URL(window.location.href);
        let matchedKey = '';
        tabs.forEach((tabEl, key) => {
          const tabUrl = new URL(tabEl.href, window.location.href);
          if (!matchedKey && tabUrl.origin === currentUrl.origin && tabUrl.pathname === currentUrl.pathname) {
            matchedKey = key;
          }
        });
        if (matchedKey) {
          const targetUrl = currentUrl.toString();
          const cachedUrl = panelUrls.get(matchedKey) || '';
          const forceReload = ['dashboard', 'people', 'groups', 'spiritual', 'msk'].includes(matchedKey)
            && panels.has(matchedKey)
            && comparableUrl(cachedUrl) !== comparableUrl(targetUrl);
          activateTab(matchedKey, { url: targetUrl, push: false, forceReload });
        }
      });

      window.addEventListener('discipleship:panel-url-change', (event) => {
        const detail = event && event.detail ? event.detail : {};
        const key = String(detail.tabKey || '').trim();
        const url = String(detail.url || '').trim();
        if (!tabs.has(key) || !url) {
          return;
        }
        try {
          const nextUrl = new URL(url, window.location.href);
          if (nextUrl.origin === window.location.origin) {
            panelUrls.set(key, nextUrl.toString());
            if (key === currentKey) {
              updateBranchLinks(nextUrl.toString());
            }
          }
        } catch (_error) {
          // Ignore malformed URLs emitted by a panel.
        }
      });

      initializePanel(initialPanel);
      showPanel(initialKey, window.location.href, false);
    };

    setupDiscipleshipWorkspace();
    document.querySelectorAll('[data-discipleship-tab-panel][data-tab-key="tree"]').forEach((panel) => {
      initDiscipleshipTreePane(panel);
    });
    setupFilePreviewModal(document);

  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootLegacyApp, { once: true });
  } else {
    bootLegacyApp();
  }
})();
