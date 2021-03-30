<?php

/**
 * The WSps extension for MediaWiki.
 *
 * @version 1.0.0 2019
 *
 * @author Sen-Sai
 *
 * @copyright Copyright (C) 2019, Sen-Sai
 *
 */


if (function_exists('wfLoadExtension')) {
    wfLoadExtension('WSPageSync');
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['WSps'] = __DIR__ . '/i18n';
    $wgExtensionMessagesFiles['WSpsAlias'] = __DIR__ . '/WSps.i18n.alias.php';
    wfWarn(
        'Deprecated PHP entry point used for WSua extension. Please use wfLoadExtension ' .
        'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return true;
} else {
    die('This version of the WSua extension requires MediaWiki 1.24+');
}
