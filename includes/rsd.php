<?php
    require_once "common.php";
    header("Content-Type: text/xml; charset=utf-8", true);

    echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName>Chyrp</engineName>
        <engineLink>http://chyrp.net/</engineLink>
        <homePageLink><?php echo $config->url; ?></homePageLink>
        <apis>
            <api name="Movable Type" preferred="true" apiLink="<?php echo $config->chyrp_url; ?>/includes/xmlrpc.php" blogID="1" />
            <api name="MetaWeblog" preferred="false" apiLink="<?php echo $config->chyrp_url; ?>/includes/xmlrpc.php" blogID="1" />
        </apis>
    </service>
</rsd>

<?php
    exit;
