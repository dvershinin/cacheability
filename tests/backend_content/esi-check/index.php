<?php
// tests/backend_content/esi-check/index.php
header('Cache-Control: public, max-age=60');
header('Surrogate-Control: content="ESI/1.0"');

// Use the WordPress ESI endpoint
$esi_url = 'http://nginx/?cacheability_esi=nonce&action=my-action&name=my-nonce-name';
?>
<!DOCTYPE html>
<html>
<head>
    <title>ESI Test PHP</title>
</head>
<body>
    <h1>Outer Content PHP</h1>
    <div id="esi-block">
        <esi:include src="<?php echo $esi_url; ?>" />
    </div>
</body>
</html>
