<?php
$dir = 'c:\\Users\\digit\\OneDrive\\Desktop\\SEO-Boster-PROJECT\\02-SOURCE-CODE\\seo-boster';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$errors = 0;
$checked = 0;
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if ($file->getExtension() === 'php') {
        $checked++;
        $output = [];
        $return_var = 0;
        exec('"' . PHP_BINARY . '" -l "' . $file->getPathname() . '"', $output, $return_var);
        if ($return_var !== 0) {
            echo 'ERROR: ' . $file->getPathname() . PHP_EOL . implode(PHP_EOL, $output) . PHP_EOL;
            $errors++;
        }
    }
}
if ($errors === 0) {
    echo "All $checked PHP files passed linting with 0 errors!" . PHP_EOL;
} else {
    echo "Found $errors errors across $checked files." . PHP_EOL;
}
