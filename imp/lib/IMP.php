<?php
/**
 * Copyright 1999-2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 1999-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * IMP base class.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Jon Parise <jon@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 1999-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP
{
    /* Encrypt constants. */
    const ENCRYPT_NONE = 'encrypt_none';

    /* IMP Mailbox view constants. */
    const MAILBOX_START_FIRSTUNSEEN = 1;
    const MAILBOX_START_LASTUNSEEN = 2;
    const MAILBOX_START_FIRSTPAGE = 3;
    const MAILBOX_START_LASTPAGE = 4;

    /* Folder list actions. */
    const NOTEPAD_EDIT = "notepad\0";
    const TASKLIST_EDIT = "tasklist\0";

    /* Initial page constants. */
    const INITIAL_FOLDERS = "initial\0folders";

    /* Sorting constants. */
    const IMAP_SORT_DATE = 100;

    /**
     * Generates a select form input from a mailbox list. The &lt;select&gt;
     * and &lt;/select&gt; tags are NOT included in the output.
     *
     * @param array $options  Optional parameters:
     *   - abbrev: (boolean) Abbreviate long mailbox names by replacing the
     *             middle of the name with '...'?
     *             DEFAULT: Yes
     *   - basename: (boolean)  Use raw basename instead of abbreviated label?
     *               DEFAULT: false
     *   - filter: (array) An array of mailboxes to ignore.
     *             DEFAULT: Display all
     *   - heading: (string) The label for an empty-value option at the top of
     *              the list.
     *              DEFAULT: ''
     *   - inc_notepads: (boolean) Include user's editable notepads in list?
     *                   DEFAULT: No
     *   - inc_tasklists: (boolean) Include user's editable tasklists in list?
     *                    DEFAULT: No
     *   - inc_vfolder: (boolean) Include user's virtual folders in list?
     *                  DEFAULT: No
     *   - new_mbox: (boolean) Display an option to create a new mailbox?
     *               DEFAULT: No
     *   - selected: (string) The mailbox to have selected by default.
     *               DEFAULT: None
     *   - optgroup: (boolean) Whether to use <optgroup> elements to group
     *               mailbox types.
     *               DEFAULT: false
     *
     * @return string  A string containing <option> elements for each mailbox
     *                 in the list.
     */
    static public function flistSelect(array $options = array())
    {
        $imaptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');
        $imaptree->setIteratorFilter();
        $tree = $imaptree->createTree(strval(new Horde_Support_Randomid()), array(
            'basename' => !empty($options['basename']),
            'render_type' => 'IMP_Tree_Flist'
        ));
        if (!empty($options['selected'])) {
            $tree->addNodeParams(IMP_Mailbox::formTo($options['selected']), array('selected' => true));
        }
        $tree->setOption($options);

        return $tree->getTree();
    }

    /**
     * Checks for To:, Subject:, Cc:, and other compose window arguments and
     * pass back an associative array of those that are present.
     *
     * @param Horde_Variables $vars  Form variables.
     *
     * @return string  An associative array with compose arguments.
     */
    static public function getComposeArgs(Horde_Variables $vars)
    {
        $args = array();
        $fields = array('to', 'cc', 'bcc', 'body', 'subject');

        foreach ($fields as $val) {
            if (isset($vars->$val)) {
                $args[$val] = $vars->$val;
            }
        }

        return self::_decodeMailto($args);
    }

    /**
     * Checks for mailto: prefix in the To field.
     *
     * @param array $args  A list of compose arguments.
     *
     * @return array  The array with the To: argument stripped of mailto:.
     */
    static protected function _decodeMailto($args)
    {
        $fields = array('to', 'cc', 'bcc', 'message', 'body', 'subject');

        if (isset($args['to']) && (strpos($args['to'], 'mailto:') === 0)) {
            $mailto = @parse_url($args['to']);
            if (is_array($mailto)) {
                $args['to'] = isset($mailto['path']) ? $mailto['path'] : '';
                if (!empty($mailto['query'])) {
                    parse_str($mailto['query'], $vals);
                    foreach ($fields as $val) {
                        if (isset($vals[$val])) {
                            $args[$val] = $vals[$val];
                        }
                    }
                }
            }
        }

        return $args;
    }

    /**
     * Returns the appropriate link to call the message composition script.
     *
     * @param mixed $args       List of arguments to pass to compose script.
     *                          If this is passed in as a string, it will be
     *                          parsed as a toaddress?subject=foo&cc=ccaddress
     *                          (mailto-style) string.
     * @param array $extra      Hash of extra, non-standard arguments to pass
     *                          to compose script.
     * @param string $simplejs  Use simple JS (instead of HordePopup JS)?
     *
     * @return Horde_Url  The link to the message composition script.
     */
    static public function composeLink($args = array(), $extra = array(),
                                       $simplejs = false)
    {
        if (is_string($args)) {
            $string = $args;
            $args = array();
            if (($pos = strpos($string, '?')) !== false) {
                parse_str(substr($string, $pos + 1), $args);
                $args['to'] = substr($string, 0, $pos);
            } else {
                $args['to'] = $string;
            }
        }

        $args = array_merge(self::_decodeMailto($args), $extra);
        $callback = $raw = false;
        $view = $GLOBALS['registry']->getView();

        if ($simplejs || ($view == Horde_Registry::VIEW_DYNAMIC)) {
            $args['popup'] = 1;

            $url = ($view == Horde_Registry::VIEW_DYNAMIC)
                ? IMP_Dynamic_Compose::url()
                : IMP_Basic_Compose::url();
            $raw = true;
            $callback = array(__CLASS__, 'composeLinkSimpleCallback');
        } elseif ($view == Horde_Registry::VIEW_SMARTMOBILE) {
            $url = new Horde_Core_Smartmobile_Url(Horde::url('smartmobile.php'));
            $url->setAnchor('compose');
        } elseif (($view != Horde_Registry::VIEW_MINIMAL) &&
                  $GLOBALS['prefs']->getValue('compose_popup') &&
                  $GLOBALS['browser']->hasFeature('javascript')) {
            $url = IMP_Basic_Compose::url();
            $callback = array(__CLASS__, 'composeLinkJsCallback');
        } else {
            $url = ($view == Horde_Registry::VIEW_MINIMAL)
                ? IMP_Minimal_Compose::url()
                : IMP_Basic_Compose::url();
        }

        if (isset($args['mailbox'])) {
            $url = IMP_Mailbox::get($args['mailbox'])->url($url, $args['buid']);
            unset($args['buid'], $args['mailbox']);
        } elseif (!($url instanceof Horde_Url)) {
            $url = Horde::url($url);
        }

        $url->setRaw($raw)->add($args);
        if ($callback) {
            $url->toStringCallback = $callback;
        }

        return $url;
    }

    /**
     * Callback for Horde_Url when generating "simple" compose links. Simple
     * links don't require exterior javascript libraries.
     *
     * @param Horde_Url $url  URL object.
     *
     * @return string  URL string representation.
     */
    static public function composeLinkSimpleCallback($url)
    {
        return "javascript:void(window.open('" . strval($url) . "','','width=820,height=610,status=1,scrollbars=yes,resizable=yes'))";
    }

    /**
     * Callback for Horde_Url when generating javascript compose links.
     *
     * @param Horde_Url $url  URL object.
     *
     * @return string  URL string representation.
     */
    static public function composeLinkJsCallback($url)
    {
        return 'javascript:' . Horde::popupJs(strval($url), array('urlencode' => true));
    }

    /**
     * Filters a string, if requested.
     *
     * @param string $text  The text to filter.
     *
     * @return string  The filtered text (if requested).
     */
    static public function filterText($text)
    {
        global $injector, $prefs;

        if ($prefs->getValue('filtering') && strlen($text)) {
            try {
                return $injector->getInstance('Horde_Core_Factory_TextFilter')->filter($text, 'words', Horde::callHook('msg_filter', array(), 'imp'));
            } catch (Horde_Exception_HookNotSet $e) {}
        }

        return $text;
    }

    /**
     * Outputs IMP's status/notification bar.
     */
    static public function status()
    {
        $GLOBALS['notification']->notify(array('listeners' => array('status', 'audio')));
    }

    /**
     * Return a list of valid encrypt HTML option tags.
     *
     * @param string $default      The default encrypt option.
     * @param boolean $returnList  Whether to return a hash with options
     *                             instead of the options tag.
     *
     * @return mixed  The list of option tags. This is empty if no encryption
     *                is available.
     */
    static public function encryptList($default = null, $returnList = false)
    {
        if (is_null($default)) {
            $default = $GLOBALS['prefs']->getValue('default_encrypt');
        }

        $enc_opts = array();
        $output = '';

        if (!empty($GLOBALS['conf']['gnupg']['path']) &&
            $GLOBALS['prefs']->getValue('use_pgp')) {
            $enc_opts += $GLOBALS['injector']->getInstance('IMP_Crypt_Pgp')->encryptList();
        }

        if ($GLOBALS['prefs']->getValue('use_smime')) {
            $enc_opts += $GLOBALS['injector']->getInstance('IMP_Crypt_Smime')->encryptList();
        }

        if (!empty($enc_opts)) {
            $enc_opts = array_merge(
                array(self::ENCRYPT_NONE => _("None")),
                $enc_opts
            );
        }

        if ($returnList) {
            return $enc_opts;
        }

        foreach ($enc_opts as $key => $val) {
             $output .= '<option value="' . $key . '"' . (($default == $key) ? ' selected="selected"' : '') . '>' . $val . "</option>\n";
        }

        return $output;
    }

    /**
     *
     * @param integer $size  The byte size of data.
     *
     * @return string  A formatted size string.
     */
    static public function sizeFormat($size)
    {
        return ($size >= 1048576)
            ? sprintf(_("%s MB"), self::numberFormat($size / 1048576, 1))
            : sprintf(_("%s KB"), self::numberFormat($size / 1024, 0));
    }

    /**
     * Workaround broken number_format() prior to PHP 5.4.0.
     *
     * @param integer $number    Number to format.
     * @param integer $decimals  Number of decimals to display.
     *
     * @return string  See number_format().
     */
    static public function numberFormat($number, $decimals)
    {
        $localeinfo = Horde_Nls::getLocaleInfo();

        return str_replace(
            array('X', 'Y'),
            array($localeinfo['decimal_point'], $localeinfo['thousands_sep']),
            number_format($number, $decimals, 'X', 'Y')
        );
    }

    /**
     * Wrapper around Horde_Mail_Rfc822#parseAddressList().
     *
     * @param string $str  The address string.
     * @param array $opts  Options to override the default.
     *
     * @return array  See Horde_Mail_Rfc822#parseAddressList().
     *
     * @throws Horde_Mail_Exception
     */
    static public function parseAddressList($str, array $opts = array())
    {
        $rfc822 = $GLOBALS['injector']->getInstance('Horde_Mail_Rfc822');
        $res = $rfc822->parseAddressList($str, array_merge(array(
            'default_domain' => $GLOBALS['injector']->getInstance('IMP_Imap')->config->maildomain,
            'validate' => false
        ), $opts));
        $res->setIteratorFilter(Horde_Mail_Rfc822_List::HIDE_GROUPS);
        return $res;
    }

    /**
     * Shortcut method to get the bare address of an e-mail string.
     *
     * @param string $str              The address string.
     * @param boolean $default_domain  Append default domain, if needed?
     *
     * @return string  The bare address.
     */
    static public function bareAddress($str, $default_domain = false)
    {
        $ob = new Horde_Mail_Rfc822_Address($str);
        if ($default_domain && is_null($ob->host)) {
            $ob->host = $GLOBALS['injector']->getInstance('IMP_Imap')->config->maildomain;
        }
        return $ob->bare_address;
    }

}
