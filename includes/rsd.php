<?php
    /**
     * File: rsd
     * Really Simple Discovery.
     */

    require_once "common.php";

    header("Content-Type: text/xml; charset=UTF-8");
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName><?php echo CHYRP_IDENTITY; ?></engineName>
        <engineLink>http://chyrplite.net/</engineLink>
        <homePageLink><?php echo url("/", MainController::current()); ?></homePageLink>
        <apis>
            <api name="MetaWeblog" preferred="true" apiLink="<?php echo $config->chyrp_url; ?>/includes/rpc.php" blogID="1" />
        </apis>
    </service>
</rsd>
