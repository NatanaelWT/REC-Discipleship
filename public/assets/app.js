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

  document.addEventListener('DOMContentLoaded', function () {
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

    const setupSuccessAlerts = () => {
      const alerts = document.querySelectorAll('.alert.success');
      alerts.forEach((alertEl, index) => {
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
        const isActivePage = document.body.classList.contains(config.bodyClass);

        wraps.forEach((wrap) => {
          if (!isActivePage || isNarrow) {
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

    const searchInputs = document.querySelectorAll('[data-filter]');
    searchInputs.forEach(input => {
      input.addEventListener('input', function () {
        filterTable(input);
      });
      input.addEventListener('change', function () {
        filterTable(input);
      });
    });

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

    const autoSubmitSearchForms = document.querySelectorAll('[data-auto-submit-search-form]');
    autoSubmitSearchForms.forEach((form) => {
      const input = form.querySelector('[data-auto-submit-search-input]');
      if (!input) {
        return;
      }
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

    const setupDiscipleshipPeopleList = () => {
      const root = document.querySelector('[data-discipleship-people-list]');
      const form = document.querySelector('[data-discipleship-people-search-form]');
      if (!root || !form) {
        return;
      }

      const input = form.querySelector('[data-discipleship-people-search-input]');
      const progressInput = form.querySelector('[data-discipleship-people-progress-input]');
      const scrollArea = root.querySelector('[data-discipleship-people-scroll]');
      const body = root.querySelector('[data-discipleship-people-list-body]');
      const emptyRow = root.querySelector('[data-discipleship-people-search-empty]');
      const loadingRow = root.querySelector('[data-discipleship-people-loading]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        total: document.querySelector('[data-people-stat="total"]'),
        dg1: document.querySelector('[data-people-stat="dg1"]'),
        dg2: document.querySelector('[data-people-stat="dg2"]'),
        dg3: document.querySelector('[data-people-stat="dg3"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextPage = parseInt(root.getAttribute('data-next-page') || '0', 10) || null;
      const perPage = parseInt(root.getAttribute('data-per-page') || '50', 10) || 50;
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;

      const currentQuery = () => String(input.value || '').trim();
      const currentProgress = () => String(progressInput ? progressInput.value : 'all').trim() || 'all';

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
        window.history.replaceState(window.history.state, '', url.toString());
      };

      const buildRowsUrl = (page) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('progress', currentProgress());
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', String(perPage));
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
        nextPage = hasMore ? (parseInt(String(data.next_page || ''), 10) || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-page', nextPage ? String(nextPage) : '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Peserta tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadPage = (page, mode) => {
        if (mode === 'append' && (!hasMore || !nextPage || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const requestId = ++requestSeq;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(page).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: activeController.signal
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
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          syncUrl();
          loadPage(1, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (hasMore && nextPage && !isLoading && nearBottom()) {
          loadPage(nextPage, 'append');
        }
      };

      input.addEventListener('input', scheduleSearch);
      if (progressInput) {
        progressInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          syncUrl();
          loadPage(1, 'replace');
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
        syncUrl();
        loadPage(1, 'replace');
      });
      if (scrollArea) {
        scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      }
      window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      window.addEventListener('resize', loadMoreIfNeeded);
      setEmptyState('Peserta tidak ditemukan.');
    };

    const setupDiscipleshipGroupsList = () => {
      const root = document.querySelector('[data-discipleship-groups-list]');
      const form = document.querySelector('[data-discipleship-groups-search-form]');
      if (!root || !form) {
        return;
      }

      const input = form.querySelector('[data-discipleship-groups-search-input]');
      const statusInput = form.querySelector('[data-discipleship-groups-status-input]');
      const scrollArea = root.querySelector('[data-discipleship-groups-scroll]');
      const body = root.querySelector('[data-discipleship-groups-list-body]');
      const emptyRow = root.querySelector('[data-discipleship-groups-empty]');
      const loadingRow = root.querySelector('[data-discipleship-groups-loading]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        total: document.querySelector('[data-groups-stat="total"]'),
        dg1: document.querySelector('[data-groups-stat="dg1"]'),
        dg2: document.querySelector('[data-groups-stat="dg2"]'),
        dg3: document.querySelector('[data-groups-stat="dg3"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextPage = parseInt(root.getAttribute('data-next-page') || '0', 10) || null;
      const perPage = parseInt(root.getAttribute('data-per-page') || '50', 10) || 50;
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;

      const currentQuery = () => String(input.value || '').trim();
      const currentStatus = () => String(statusInput ? statusInput.value : 'all').trim() || 'all';

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
        window.history.replaceState(window.history.state, '', url.toString());
      };

      const buildRowsUrl = (page) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('status', currentStatus());
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', String(perPage));
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
        nextPage = hasMore ? (parseInt(String(data.next_page || ''), 10) || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-page', nextPage ? String(nextPage) : '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Kelompok tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadPage = (page, mode) => {
        if (mode === 'append' && (!hasMore || !nextPage || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const requestId = ++requestSeq;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(page).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: activeController.signal
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
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          syncUrl();
          loadPage(1, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (hasMore && nextPage && !isLoading && nearBottom()) {
          loadPage(nextPage, 'append');
        }
      };

      input.addEventListener('input', scheduleSearch);
      if (statusInput) {
        statusInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          syncUrl();
          loadPage(1, 'replace');
        });
      }
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
        }
        syncUrl();
        loadPage(1, 'replace');
      });
      if (scrollArea) {
        scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      }
      window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      window.addEventListener('resize', loadMoreIfNeeded);
      setEmptyState('Kelompok tidak ditemukan.');
    };

    const setupMskList = () => {
      const root = document.querySelector('[data-msk-list]');
      const form = document.querySelector('[data-msk-search-form]');
      if (!root || !form) {
        return;
      }

      const input = form.querySelector('[data-msk-search-input]');
      const batchInput = document.querySelector('[data-msk-batch-input]');
      const scrollArea = root.querySelector('[data-msk-scroll]');
      const body = root.querySelector('[data-msk-list-body]');
      const emptyRow = root.querySelector('[data-msk-search-empty]');
      const loadingRow = root.querySelector('[data-msk-loading]');
      const templatesContainer = document.querySelector('[data-msk-view-templates]');
      const editTemplatesContainer = document.querySelector('[data-msk-edit-templates]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        filter: document.querySelector('[data-msk-stat="filter"]'),
        total: document.querySelector('[data-msk-stat="total"]'),
        complete: document.querySelector('[data-msk-stat="complete"]'),
        progress: document.querySelector('[data-msk-stat="progress"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextPage = parseInt(root.getAttribute('data-next-page') || '0', 10) || null;
      const perPage = parseInt(root.getAttribute('data-per-page') || '50', 10) || 50;
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;

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
        document.querySelectorAll('input[name="batch_month"]').forEach((node) => {
          node.value = batch;
        });
      };

      const syncSearchHiddenInputs = () => {
        const query = currentQuery();
        document.querySelectorAll('input[type="hidden"][name="q"]').forEach((node) => {
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
        url.searchParams.delete('edit');
        url.searchParams.delete('view');
        window.history.replaceState(window.history.state, '', url.toString());
      };

      const buildRowsUrl = (page) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]') || document.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('batch_month', currentBatch());
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', String(perPage));
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
        nextPage = hasMore ? (parseInt(String(data.next_page || ''), 10) || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-page', nextPage ? String(nextPage) : '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Peserta tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadPage = (page, mode) => {
        if (mode === 'append' && (!hasMore || !nextPage || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const requestId = ++requestSeq;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(page).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: activeController.signal
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
          syncUrl();
          loadPage(1, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (hasMore && nextPage && !isLoading && nearBottom()) {
          loadPage(nextPage, 'append');
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
          syncUrl();
          loadPage(1, 'replace');
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
        syncUrl();
        loadPage(1, 'replace');
      });
      if (scrollArea) {
        scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      }
      window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      window.addEventListener('resize', loadMoreIfNeeded);
      setEmptyState('Peserta tidak ditemukan.');
    };


    const setupSpiritualJourneyList = () => {
      const root = document.querySelector('[data-spiritual-journey-list]');
      const form = document.querySelector('[data-spiritual-journey-search-form]');
      if (!root || !form) {
        return;
      }

      const input = form.querySelector('[data-spiritual-journey-search-input]');
      const filterInput = form.querySelector('[data-spiritual-journey-filter-input]');
      const scrollArea = root.querySelector('[data-spiritual-journey-scroll]');
      const body = root.querySelector('[data-spiritual-journey-list-body]');
      const emptyRow = root.querySelector('[data-spiritual-journey-search-empty]');
      const loadingRow = root.querySelector('[data-spiritual-journey-loading]');
      const templatesContainer = document.querySelector('[data-spiritual-journey-view-templates]');
      const rowsUrl = root.getAttribute('data-rows-url') || '';
      if (!input || !body || !rowsUrl) {
        return;
      }

      const statNodes = {
        dg1: document.querySelector('[data-spiritual-journey-stat="dg1"]'),
        kgap: document.querySelector('[data-spiritual-journey-stat="kgap"]'),
        dg2: document.querySelector('[data-spiritual-journey-stat="dg2"]'),
        dg3: document.querySelector('[data-spiritual-journey-stat="dg3"]')
      };
      let hasMore = root.getAttribute('data-has-more') === '1';
      let nextPage = parseInt(root.getAttribute('data-next-page') || '0', 10) || null;
      const perPage = parseInt(root.getAttribute('data-per-page') || '50', 10) || 50;
      let activeController = null;
      let requestSeq = 0;
      let isLoading = false;
      let searchTimer = null;

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
        url.searchParams.delete('edit');
        url.searchParams.delete('view');
        window.history.replaceState(window.history.state, '', url.toString());
      };

      const buildRowsUrl = (page) => {
        const url = new URL(rowsUrl, window.location.origin);
        const branchInput = form.querySelector('input[name="branch_id"]') || document.querySelector('input[name="branch_id"]');
        const query = currentQuery();
        if (query) {
          url.searchParams.set('q', query);
        }
        url.searchParams.set('journey_filter', currentFilter());
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', String(perPage));
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
        nextPage = hasMore ? (parseInt(String(data.next_page || ''), 10) || null) : null;
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        root.setAttribute('data-next-page', nextPage ? String(nextPage) : '');
        updateStats(data ? data.stats : null);
        setEmptyState(data ? data.empty_message : 'Peserta tidak ditemukan.');
        scheduleViewportTableHeights();
      };

      const loadPage = (page, mode) => {
        if (mode === 'append' && (!hasMore || !nextPage || isLoading)) {
          return;
        }
        if (activeController) {
          activeController.abort();
        }
        activeController = new AbortController();
        const requestId = ++requestSeq;
        isLoading = true;
        setLoading(true);
        window.fetch(buildRowsUrl(page).toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: activeController.signal
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
            setLoading(false);
          });
      };

      const scheduleSearch = () => {
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(() => {
          searchTimer = null;
          syncUrl();
          loadPage(1, 'replace');
        }, 250);
      };

      const nearBottom = () => {
        if (scrollArea && scrollArea.scrollHeight > scrollArea.clientHeight) {
          return scrollArea.scrollTop + scrollArea.clientHeight >= scrollArea.scrollHeight - 120;
        }

        return root.getBoundingClientRect().bottom <= (window.innerHeight || document.documentElement.clientHeight || 0) + 180;
      };

      const loadMoreIfNeeded = () => {
        if (hasMore && nextPage && !isLoading && nearBottom()) {
          loadPage(nextPage, 'append');
        }
      };

      input.addEventListener('input', scheduleSearch);
      if (filterInput) {
        filterInput.addEventListener('change', () => {
          if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
          }
          syncUrl();
          loadPage(1, 'replace');
        });
      }
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (searchTimer !== null) {
          window.clearTimeout(searchTimer);
          searchTimer = null;
        }
        syncUrl();
        loadPage(1, 'replace');
      });
      if (scrollArea) {
        scrollArea.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      }
      window.addEventListener('scroll', loadMoreIfNeeded, { passive: true });
      window.addEventListener('resize', loadMoreIfNeeded);
      setEmptyState('Peserta tidak ditemukan.');
    };

    setupSpiritualJourneyList();
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

    const mskViewModal = document.querySelector('[data-msk-view-modal]');
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

    const mskEditModal = document.querySelector('[data-msk-edit-modal]');
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

    const spiritualJourneyViewModal = document.querySelector('[data-spiritual-journey-view-modal]');
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

    const treeV2HistoryModal = document.querySelector('[data-tree-v2-history-modal]');
    if (treeV2HistoryModal) {
      const titleEl = treeV2HistoryModal.querySelector('[data-tree-v2-history-title]');
      const bodyEl = treeV2HistoryModal.querySelector('[data-tree-v2-history-body]');
      const closeButtons = treeV2HistoryModal.querySelectorAll('[data-tree-v2-history-close]');
      const templateMap = new Map();

      document.querySelectorAll('template[data-tree-v2-history-template]').forEach((templateEl) => {
        const groupKey = templateEl.getAttribute('data-tree-v2-history-template') || '';
        if (groupKey) {
          templateMap.set(groupKey, templateEl);
        }
      });

      const openTreeV2History = (groupKey) => {
        const key = String(groupKey || '').trim();
        if (!key || !templateMap.has(key)) {
          return;
        }
        const templateEl = templateMap.get(key);
        const modalTitle = templateEl?.getAttribute('data-tree-v2-history-template-title') || 'Riwayat Kelompok';
        const templateHtml = templateEl ? templateEl.innerHTML : '';

        if (titleEl) {
          titleEl.textContent = modalTitle;
        }
        if (bodyEl) {
          bodyEl.innerHTML = templateHtml;
        }

        treeV2HistoryModal.classList.add('is-open');
        treeV2HistoryModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };

      const closeTreeV2History = () => {
        treeV2HistoryModal.classList.remove('is-open');
        treeV2HistoryModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      };

      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-tree-v2-history-open]');
        if (!trigger) {
          return;
        }
        event.preventDefault();
        openTreeV2History(trigger.getAttribute('data-tree-v2-history-open') || '');
      });

      document.addEventListener('keydown', function (event) {
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

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && treeV2HistoryModal.classList.contains('is-open')) {
          closeTreeV2History();
        }
      });
    }

    const mskCreateModal = document.querySelector('[data-msk-create-modal]');
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

    const modal = document.querySelector('[data-modal]');
    if (modal) {
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
        while (current && current !== document) {
          if (current.getAttribute && current.getAttribute('data-modal-open')) {
            return current;
          }
          current = current.parentElement;
        }
        return null;
      };

      document.addEventListener('click', function (event) {
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

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          closeModal();
        }
      });

      const editId = modal.getAttribute('data-edit-id');
      if (editId) {
        const autoBtn = document.querySelector('[data-modal-open="edit"][data-person-id="' + editId + '"]');
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
    }

    const groupModal = document.querySelector('[data-group-modal]');
    if (groupModal) {
      const titleEl = groupModal.querySelector('[data-group-title]');
      const addForm = groupModal.querySelector('[data-group-form="add"]');
      const editForm = groupModal.querySelector('[data-group-form="edit"]');
      const closeButtons = groupModal.querySelectorAll('[data-group-close]');
      const groupMemberSourceMap = new Map();

      document.querySelectorAll('script[data-group-member-source]').forEach((sourceEl) => {
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

      document.addEventListener('click', function (event) {
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

      document.addEventListener('keydown', function (event) {
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
    }

    const treeV2ActionModal = document.querySelector('[data-tree-v2-action-modal]');
    const personProfileModal = document.querySelector('[data-tree-v2-person-profile-modal]');
    if (treeV2ActionModal || personProfileModal) {
      const titleEl = treeV2ActionModal ? treeV2ActionModal.querySelector('[data-tree-v2-action-title]') : null;
      const closeButtons = treeV2ActionModal ? treeV2ActionModal.querySelectorAll('[data-tree-v2-action-close]') : [];
      const actionButtons = treeV2ActionModal ? treeV2ActionModal.querySelectorAll('[data-tree-v2-action-do]') : [];
      const personProfileTitleEl = personProfileModal ? personProfileModal.querySelector('[data-tree-v2-person-profile-title]') : null;
      const personProfileBodyEl = personProfileModal ? personProfileModal.querySelector('[data-tree-v2-person-profile-body]') : null;
      const personProfileCloseButtons = personProfileModal ? personProfileModal.querySelectorAll('[data-tree-v2-person-profile-close]') : [];
      const personProfileActionButtons = personProfileModal ? personProfileModal.querySelectorAll('[data-tree-v2-profile-action]') : [];
      const personProfileTemplates = new Map();
      const addMemberProxy = document.querySelector('[data-tree-v2-proxy="add-member"]');
      const editPersonProxy = document.querySelector('[data-tree-v2-proxy="edit-person"]');
      const addGroupProxy = document.querySelector('[data-tree-v2-proxy="add-group"]');
      const viewHistoryProxy = document.querySelector('[data-tree-v2-proxy="view-history"]');
      const leaveGroupForm = document.querySelector('[data-tree-v2-leave-form]');
      const deletePersonForm = document.querySelector('[data-tree-v2-delete-person-form]');
      const completeGroupForm = document.querySelector('[data-tree-v2-complete-group-form]');
      const reactivateGroupForm = document.querySelector('[data-tree-v2-reactivate-group-form]');
      const buttonsByAction = {};
      actionButtons.forEach(button => {
        const action = button.getAttribute('data-tree-v2-action-do') || '';
        if (action !== '') {
          buttonsByAction[action] = button;
        }
      });
      document.querySelectorAll('template[data-tree-v2-person-profile-template]').forEach(templateEl => {
        const personKey = templateEl.getAttribute('data-tree-v2-person-profile-template') || '';
        if (personKey !== '') {
          personProfileTemplates.set(personKey, templateEl);
        }
      });

      let activeNode = null;
      let activeNodeData = null;

      const setActionVisible = (action, visible) => {
        const button = buttonsByAction[action];
        if (!button) return;
        if (visible) {
          button.classList.remove('is-hidden');
          button.disabled = false;
        } else {
          button.classList.add('is-hidden');
          button.disabled = true;
        }
      };

      const closeActionModal = () => {
        if (treeV2ActionModal) {
          treeV2ActionModal.classList.remove('is-open');
          treeV2ActionModal.setAttribute('aria-hidden', 'true');
        }
        if (personProfileModal) {
          personProfileModal.classList.remove('is-open');
          personProfileModal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('modal-open');
        activeNode = null;
        activeNodeData = null;
      };

      const clickProxy = proxyButton => {
        if (!proxyButton) return;
        closeActionModal();
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

      const openPersonProfileModal = (node, nodeData) => {
        if (!personProfileModal || !personProfileBodyEl) return false;
        const personId = String(nodeData.personId || '').trim();
        if (!personId || nodeData.isRoot) return false;
        let templateEl = personProfileTemplates.get(personId);
        if (!templateEl) {
          templateEl = queryTemplateByAttribute('data-tree-v2-person-profile-template', personId);
          if (templateEl) {
            personProfileTemplates.set(personId, templateEl);
          }
        }
        if (!templateEl) return false;

        activeNode = node;
        activeNodeData = nodeData;

        if (personProfileTitleEl) {
          personProfileTitleEl.textContent = templateEl.getAttribute('data-tree-v2-person-profile-template-title') || nodeData.name || 'Profil Orang';
        }
        personProfileBodyEl.innerHTML = templateEl.innerHTML;

        const groupContext = currentPersonGroupContext();
        setProfileActionVisible('add_group', !nodeData.isRoot && personId !== '');
        setProfileActionVisible('edit_person', !nodeData.isRoot && personId !== '');
        setProfileActionVisible('delete_person', !nodeData.isRoot && personId !== '');
        setProfileActionVisible('leave_group', !nodeData.isRoot && groupContext.id !== '' && groupContext.status === 'active');

        personProfileModal.classList.add('is-open');
        personProfileModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
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

        treeV2ActionModal.classList.add('is-open');
        treeV2ActionModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
      };

      document.addEventListener('click', function (event) {
        const node = event.target.closest('[data-tree-v2-node-action]');
        if (!node) return;
        event.preventDefault();
        openActionModal(node);
      });

      document.addEventListener('keydown', function (event) {
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
          if (event.target === personProfileModal) {
            closeActionModal();
          }
        });
      }

      document.addEventListener('keydown', function (event) {
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
          clickProxy(viewHistoryProxy);
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
    }

    const filePreviewModal = document.querySelector('[data-file-preview-modal]');
    if (filePreviewModal) {
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

      document.addEventListener('click', function (event) {
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

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && filePreviewModal.classList.contains('is-open')) {
          closePreviewModal();
        }
      });
    }

    const dragScrollAreas = document.querySelectorAll('[data-drag-scroll]');
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

    const zoomTarget = document.querySelector('[data-tree-zoom]');
    const zoomControls = document.querySelector('[data-zoom-controls]');
    const treeScrollArea = document.querySelector('[data-drag-scroll]');
    const treeViewportStorageKey = 'people_tree_viewport_state';
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

    const treeSearchInput = document.querySelector('[data-tree-search-input]');
    const treeSearchSubmit = document.querySelector('[data-tree-search-submit]');
    if (treeScrollArea && treeSearchInput && treeSearchSubmit) {
      let searchHighlightTimer = null;

      const clearSearchHighlight = () => {
        document.querySelectorAll('.tree-v2-node.is-search-hit').forEach((node) => {
          node.classList.remove('is-search-hit');
        });
      };

      const normalizeSearchText = (value) => String(value || '').trim().toLowerCase();

      const findSearchNode = (query) => {
        const normalizedQuery = normalizeSearchText(query);
        if (!normalizedQuery) {
          return null;
        }
        const nodeEls = Array.from(document.querySelectorAll('.tree-v2-node[data-search-name]'));
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

      document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
          saveTreeViewportState();
        });
      });

      window.addEventListener('beforeunload', () => {
        saveTreeViewportState();
      });
    }

  });
})();
