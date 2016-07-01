<?php
    require_once "common.php";
    header("Content-Type: text/xml; charset=UTF-8");
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName><?php echo "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")"; ?></engineName>
        <engineLink>http://chyrplite.net/</engineLink>
        <homePageLink><?php echo $config->url; ?></homePageLink>
        <apis>
            <api name="MetaWeblog" preferred="true" apiLink="<?php echo $config->chyrp_url; ?>/includes/rpc.php" blogID="1" />
            <api name="Blogger" preferred="false" apiLink="<?php echo $config->chyrp_url; ?>/includes/rpc.php" blogID="1" />
            <api name="Movable Type" preferred="false" apiLink="<?php echo $config->chyrp_url; ?>/includes/rpc.php" blogID="1" />
        </apis>
    </service>
</rsd>
