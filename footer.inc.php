    <footer class="footer mt-auto py-3 bg-body-tertiary">
        <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between text-center text-md-start gap-2">
            <span class="text-body-secondary small">
                Built with <i class="bi bi-heart-fill text-danger" aria-label="love"></i> in Siena, Italy
            </span>
            <div class="small">
                <a href="/privacy" class="text-body-secondary text-decoration-none hover-underline">
                    <i class="bi bi-shield-lock me-1"></i>Privacy Policy
                </a>
            </div>
        </div>
    </footer>

    <!-- JavaScript Dependencies -->
    <script src="/js/jquery.min.js" defer></script>
    <script src="/js/bootstrap.bundle.min.js" defer></script>
    <script src="/js/bootstrap-table.min.js" defer></script>
    <script src="/js/chart.js" defer></script>
    <script src="/js/common.js" defer></script>
<?php
$currentPageScript = pathinfo(basename($_SERVER['SCRIPT_NAME']), PATHINFO_FILENAME);

$customJsFile = sprintf('00-%s.js', $currentPageScript);
$customJsPath = __DIR__ . '/js/' . $customJsFile;
$customJsUrl  = '/js/' . $customJsFile;

if (file_exists($customJsPath)) {
    $version = filemtime($customJsPath);
    echo '    <script src="' . htmlspecialchars($customJsUrl, ENT_QUOTES, 'UTF-8') . '?v=' . $version . '"></script>' . PHP_EOL;
}

?>
</body>
</html>
