<script>
(function () {
  var shell = document.querySelector('[data-public-material-pdf-viewer]');
  var userAgent = navigator.userAgent || '';
  var isIos = /iPad|iPhone|iPod/i.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (!shell || !(/Android/i.test(userAgent) || isIos)) { return; }
  var pdfUrl = shell.getAttribute('data-pdf-url') || '';
  var nativePdf = shell.querySelector('[data-native-pdf]');
  var viewer = shell.querySelector('[data-pdfjs-viewer]');
  var status = shell.querySelector('[data-pdfjs-status]');
  var pages = shell.querySelector('[data-pdfjs-pages]');
  var fallback = shell.querySelector('[data-pdfjs-fallback]');
  if (!pdfUrl || !viewer || !pages) { return; }
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
  function renderPage(pdf, pageNumber) {
    return pdf.getPage(pageNumber).then(function (page) {
      var baseViewport = page.getViewport({ scale: 1 });
      var availableWidth = Math.max(240, pages.clientWidth - 16);
      var scale = Math.min(2, Math.max(0.7, availableWidth / baseViewport.width));
      var viewport = page.getViewport({ scale: scale });
      var ratio = Math.min(window.devicePixelRatio || 1, 2);
      var canvas = document.createElement('canvas');
      var context = canvas.getContext('2d');
      canvas.className = 'public-material-pdfjs-page';
      canvas.width = Math.floor(viewport.width * ratio);
      canvas.height = Math.floor(viewport.height * ratio);
      canvas.style.width = Math.floor(viewport.width) + 'px';
      canvas.style.height = Math.floor(viewport.height) + 'px';
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
      pages.appendChild(canvas);
      return page.render({ canvasContext: context, viewport: viewport }).promise;
    });
  }
  loadScript('assets/vendor/pdfjs/pdf.min.js', function (error) {
    if (error || !window.pdfjsLib) { showError(); return; }
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdfjs/pdf.worker.min.js';
    fetch(pdfUrl, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) { throw new Error('pdf-fetch-failed'); }
      return response.arrayBuffer();
    }).then(function (buffer) {
      return window.pdfjsLib.getDocument({ data: buffer }).promise;
    }).then(function (pdf) {
      if (status) { status.textContent = 'Memuat ' + pdf.numPages + ' halaman...'; }
      var sequence = Promise.resolve();
      for (var pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        (function (number) { sequence = sequence.then(function () { return renderPage(pdf, number); }); })(pageNumber);
      }
      sequence.then(function () { if (status) { status.hidden = true; } }).catch(showError);
    }).catch(showError);
  });
}());
</script>
