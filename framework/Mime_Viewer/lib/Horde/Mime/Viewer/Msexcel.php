<?php
/**
 * The Horde_Mime_Viewer_Msexcel class renders out Microsoft Excel
 * documents in HTML format by using the Gnumeric package.
 *
 * Copyright 1999-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author   Anil Madhavapeddy <anil@recoil.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @package  Mime_Viewer
 */
class Horde_Mime_Viewer_Msexcel extends Horde_Mime_Viewer_Base
{
    /**
     * This driver's display capabilities.
     *
     * @var array
     */
    protected $_capability = array(
        'full' => true,
        'info' => false,
        'inline' => false,
        'raw' => false
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * @return array  See parent::render().
     */
    protected function _render()
    {
        /* Check to make sure the viewer program exists. */
        if (!isset($this->_conf['location']) ||
            !file_exists($this->_conf['location'])) {
            return array();
        }

        $tmp_in = Horde::getTempFile('horde_msexcel');
        $tmp_out = Horde::getTempFile('horde_msexcel');

        file_put_contents($tmp_in, $this->_mimepart->getContents());
        $args = ' -E Gnumeric_Excel:excel_dsf -T Gnumeric_html:html40 ' . $tmp_in . ' ' . $tmp_out;

        exec($this->_conf['location'] . $args);

        return $this->_renderReturn(
            file_get_contents($tmp_out),
            'text/html; charset=' . $GLOBALS['registry']->getCharset()
        );
    }

}
