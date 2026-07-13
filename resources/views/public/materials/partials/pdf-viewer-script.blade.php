<script>
(function () {
  var shell = document.querySelector('[data-public-material-pdf-viewer]');
  if (!shell) { return; }
  var userAgent = navigator.userAgent || '';
  var isIos = /iPad|iPhone|iPod/i.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var usePdfJs = /Android/i.test(userAgent) || isIos;
  var pdfUrl = shell.getAttribute('data-pdf-url') || '';
  var pdfTitle = shell.getAttribute('data-pdf-title') || 'Preview PDF';
  var pdfJsUrl = shell.getAttribute('data-pdfjs-url') || '';
  var workerUrl = shell.getAttribute('data-pdfjs-worker-url') || '';
  var nativePdf = shell.querySelector('[data-native-pdf]');
  var viewer = shell.querySelector('[data-pdfjs-viewer]');
  var status = shell.querySelector('[data-pdfjs-status]');
  var pages = shell.querySelector('[data-pdfjs-pages]');
  var fallback = shell.querySelector('[data-pdfjs-fallback]');
  if (!pdfUrl || !viewer || !pages) { return; }

  if (!usePdfJs) {
    if (!nativePdf) { return; }
    nativePdf.innerHTML = '';
    var iframe = document.createElement('iframe');
    iframe.className = 'file-page-embed public-material-preview-embed';
    iframe.src = pdfUrl;
    iframe.loading = 'eager';
    iframe.referrerPolicy = 'same-origin';
    iframe.title = pdfTitle;
    nativePdf.appendChild(iframe);
    nativePdf.hidden = false;
    return;
  }

  if (nativePdf) { nativePdf.hidden = true; }
  viewer.hidden = false;
  function showError() {
    if (status) { status.hidden = true; }
    if (fallback) { fallback.hidden = false; }
  }
  function loadScript(src, done) {
    var script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = function () { done(null); };
    script.onerror = function () { done(new Error('pdfjs-load-failed')); };
    document.head.appendChild(script);
  }
  function renderPage(pdf, placeholder) {
    if (!placeholder || placeholder.getAttribute('data-render-state') !== 'idle') { return Promise.resolve(); }
    placeholder.setAttribute('data-render-state', 'loading');
    var pageNumber = parseInt(placeholder.getAttribute('data-page-number') || '0', 10);
    return pdf.getPage(pageNumber).then(function (page) {
      var baseViewport = page.getViewport({ scale: 1 });
      var availableWidth = Math.max(240, pages.clientWidth - 16);
      var scale = Math.min(2, Math.max(0.7, availableWidth / baseViewport.width));
      var viewport = page.getViewport({ scale: scale });
      var maxPixels = 4000000;
      var pixelRatioCap = Math.sqrt(maxPixels / Math.max(1, viewport.width * viewport.height));
      var ratio = Math.max(1, Math.min(window.devicePixelRatio || 1, 1.5, pixelRatioCap));
      var canvas = document.createElement('canvas');
      var context = canvas.getContext('2d');
      if (!context) { throw new Error('canvas-unavailable'); }
      canvas.className = 'public-material-pdfjs-page';
      canvas.width = Math.floor(viewport.width * ratio);
      canvas.height = Math.floor(viewport.height * ratio);
      canvas.style.width = Math.floor(viewport.width) + 'px';
      canvas.style.height = Math.floor(viewport.height) + 'px';
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
      placeholder.style.minHeight = Math.floor(viewport.height) + 'px';
      placeholder.replaceChildren(canvas);
      return page.render({ canvasContext: context, viewport: viewport }).promise.then(function () {
        placeholder.setAttribute('data-render-state', 'rendered');
        if (typeof page.cleanup === 'function') { page.cleanup(); }
      });
    }).catch(function (error) {
      placeholder.setAttribute('data-render-state', 'idle');
      throw error;
    });
  }

  function releasePage(placeholder) {
    if (!placeholder || placeholder.getAttribute('data-render-state') !== 'rendered') { return; }
    var rect = placeholder.getBoundingClientRect();
    var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    if (rect.bottom >= -viewportHeight * 2 && rect.top <= viewportHeight * 3) { return; }
    var canvas = placeholder.querySelector('canvas');
    if (canvas) {
      canvas.width = 0;
      canvas.height = 0;
    }
    placeholder.replaceChildren();
    placeholder.setAttribute('data-render-state', 'idle');
  }

  loadScript(pdfJsUrl, function (error) {
    if (error || !window.pdfjsLib) { showError(); return; }
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
    window.pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise.then(function (pdf) {
      if (status) { status.textContent = 'Menyiapkan ' + pdf.numPages + ' halaman...'; }
      var placeholders = [];
      for (var pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        var placeholder = document.createElement('div');
        placeholder.className = 'public-material-pdfjs-page-placeholder';
        placeholder.setAttribute('data-page-number', String(pageNumber));
        placeholder.setAttribute('data-render-state', 'idle');
        placeholder.setAttribute('aria-label', 'Halaman ' + pageNumber);
        placeholder.style.minHeight = Math.max(360, Math.round((pages.clientWidth - 16) * 1.414)) + 'px';
        pages.appendChild(placeholder);
        placeholders.push(placeholder);
      }
      if (status) { status.hidden = true; }

      if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              renderPage(pdf, entry.target).catch(showError);
            } else {
              releasePage(entry.target);
            }
          });
        }, { root: viewer, rootMargin: '900px 0px' });
        placeholders.forEach(function (placeholder) { observer.observe(placeholder); });
        window.addEventListener('pagehide', function () {
          observer.disconnect();
          if (typeof pdf.destroy === 'function') { pdf.destroy(); }
        }, { once: true });
      } else {
        var scanTimer = null;
        var scanPages = function () {
          scanTimer = null;
          placeholders.forEach(function (placeholder) {
            var rect = placeholder.getBoundingClientRect();
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            if (rect.bottom >= -600 && rect.top <= viewportHeight + 600) {
              renderPage(pdf, placeholder).catch(showError);
            } else {
              releasePage(placeholder);
            }
          });
        };
        viewer.addEventListener('scroll', function () {
          if (scanTimer === null) { scanTimer = window.setTimeout(scanPages, 80); }
        }, { passive: true });
        scanPages();
      }
    }).catch(showError);
  });
}());
</script>
