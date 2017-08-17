<?php
/**
 * Allow creation of XHTML definition lists:
 * <dl>
 *   <dt>term</dt>
 *   <dd>definition</dd>
 * </dl>
 *
 * Syntax:
 *   ; term : definition
 *   ; term
 *   : definition
 *
 * As with other dokuwiki lists, each line must start with 2 spaces or a tab.
 * Nested definition lists are not supported at this time.
 *
 * This plugin is heavily based on the definitions plugin by Pavel Vitis which
 * in turn drew from the original definition list plugin by Stephane Chamberland.
 * A huge thanks to both of them.
 *
 * Configuration:
 *
 * dt_fancy    Whether to wrap DT content in <span class="term">Term</span>.
 *             Default true.
 * classname   The html class name to be given to the DL element.
 *             Default 'plugin_definitionlist'. This is the class used in the
 *             bundled CSS file.
 *
 * ODT support provided by Gabriel Birke and LarsDW223
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Chris Smith <chris [at] jalakai [dot] co [dot] uk>
 * @author     Gabriel Birke <birke@d-scribe.de>
 */

if (!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * Settings:
 *
 * Define the trigger characters:
 * ";" & ":" are the mediawiki settings.
 * "=" & ":" are the settings for the original plugin by Pavel.
 */
if (!defined('DL_DT')) define('DL_DT', ';'); // character to indicate a term (dt)
if (!defined('DL_DD')) define('DL_DD', ':'); // character to indicate a definition (dd)

/**
 *
 */
class syntax_plugin_definitionlist extends DokuWiki_Syntax_Plugin {

    protected $stack = array();    // stack of currently open definition list items - used by handle() method

    public function getType() { return 'container'; }
    public function getAllowedTypes() { return array('container','substition','protected','disabled','formatting'); }
    public function getPType() { return 'block'; }          // block, so not surrounded by <p> tags
    public function getSort() { return 10; }                // before preformatted (20)

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {

        $this->Lexer->addEntryPattern('\n {2,}'.DL_DT, $mode, 'plugin_definitionlist');
        $this->Lexer->addEntryPattern('\n\t{1,}'.DL_DT, $mode, 'plugin_definitionlist');

        $this->Lexer->addPattern('(?: '.DL_DD.' )', 'plugin_definitionlist');
        $this->Lexer->addPattern('\n {2,}(?:'.DL_DT.'|'.DL_DD.')', 'plugin_definitionlist');
        $this->Lexer->addPattern('\n\t{1,}(?:'.DL_DT.'|'.DL_DD.')', 'plugin_definitionlist');
    }

    public function postConnect() {
        // we end the definition list when we encounter a blank line
        $this->Lexer->addExitPattern('\n(?=[ \t]*\n)','plugin_definitionlist');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        switch ( $state ) {
            case DOKU_LEXER_ENTER:
                    array_push($this->stack, 'dt');
                    $this->_writeCall('dl',DOKU_LEXER_ENTER,$pos,$match,$handler);    // open a new DL
                    $this->_writeCall('dt',DOKU_LEXER_ENTER,$pos,$match,$handler);    // always start with a DT
                    break;

            case DOKU_LEXER_MATCHED:
                    $oldtag = array_pop($this->stack);
                    $newtag = (substr(rtrim($match), -1) == DL_DT) ? 'dt' : 'dd';
                    array_push($this->stack, $newtag);

                    $this->_writeCall($oldtag,DOKU_LEXER_EXIT,$pos,$match,$handler);  // close the current definition list item...
                    $this->_writeCall($newtag,DOKU_LEXER_ENTER,$pos,$match,$handler); // ...and open the new dl item
                    break;

            case DOKU_LEXER_EXIT:
                    // clean up & close any dl items on the stack
                    while ($tag = array_pop($this->stack)) {
                        $this->_writeCall($tag,DOKU_LEXER_EXIT,$pos,$match,$handler);
                    }

                    // and finally close the surrounding DL
                    $this->_writeCall('dl',DOKU_LEXER_EXIT,$pos,$match,$handler);
                    break;

            case DOKU_LEXER_UNMATCHED:
                    $handler->base($match, $state, $pos);    // cdata --- use base() as _writeCall() is prefixed for private/protected
                    break;
        }

        return false;
    }

    /**
     * helper function to simplify writing plugin calls to the instruction list
     *
     * instruction params are of the format:
     *    0 => tag    (string)    'dl','dt','dd'
     *    1 => state  (int)       DOKU_LEXER_??? state constant
     *    2 => match  (string)    expected to be empty
     */
    protected function _writeCall($tag, $state, $pos, $match, &$handler) {
        $handler->addPluginCall('definitionlist', array($tag, $state, ''), $state, $pos, $match);
    }

    /**
     * Create output
     */
    public function render($format, Doku_Renderer $renderer, $data) {
        if (empty($data)) return false;

        switch  ($format) {
            case 'xhtml' : return $this->render_xhtml($renderer,$data);
            case 'odt'   :
                if (!method_exists ($renderer, 'getODTPropertiesFromElement')) {
                    return $this->render_odt_old($renderer,$data);
                } else {
                    return $this->render_odt_new($renderer,$data);
                }
            default :
                //  handle unknown formats generically - map both 'dt' & 'dd' to paragraphs; ingnore the 'dl' container
                list ($tag, $state, $match) = $data;
                switch ( $state ) {
                    case DOKU_LEXER_ENTER:
                    if ($tag != 'dl') $renderer->p_open();
                    break;
                case DOKU_LEXER_MATCHED:                              // fall-thru
                case DOKU_LEXER_UNMATCHED:                            // defensive, shouldn't occur
                    $renderer->cdata($match);
                    break;
                case DOKU_LEXER_EXIT:
                    if ($tag != 'dl') $renderer->p_close();
                    break;
                }
                return true;
        }

        return false;
    }

    /**
     * create output for the xhtml renderer
     *
     */
    protected function render_xhtml(Doku_Renderer $renderer, $data) {
        list($tag,$state,$match) = $data;

        switch ( $state ) {
            case DOKU_LEXER_ENTER:
                $renderer->doc .= $this->_open($tag);
                break;
            case DOKU_LEXER_MATCHED:
            case DOKU_LEXER_UNMATCHED:                            // defensive, shouldn't occur
                $renderer->cdata($tag);
                break;
            case DOKU_LEXER_EXIT:
                $renderer->doc .= $this->_close($tag);
                break;
        }
        return true;
    }

    /**
     * create output for ODT renderer
     *
     * @author:   Gabriel Birke <birke@d-scribe.de>
     */
    protected function render_odt_old(Doku_Renderer $renderer, $data) {
        static $param_styles = array('dd' => 'def_f5_list', 'dt' => 'def_f5_term');
        $this->_set_odt_styles_old($renderer);

        list ($tag, $state, $match) = $data;

        switch ( $state ) {
            case DOKU_LEXER_ENTER:
                if ($tag == 'dl') {
                    $renderer->p_close();
                } else {
                    $renderer->p_open($param_styles[$tag]);
                }
                break;
            case DOKU_LEXER_MATCHED:
            case DOKU_LEXER_UNMATCHED:                            // defensive, shouldn't occur
                $renderer->cdata($match);
                break;
            case DOKU_LEXER_EXIT:
                if ($tag != 'dl') {
                    $renderer->p_close();
                } else {
                    $renderer->p_open();
                }
                break;
        }

        return true;
    }

    /**
     * Create output for ODT renderer (newer version)
     * @author: LarsDW223
     */
    protected function render_odt_new(Doku_Renderer $renderer, $data) {
        static $style_data = array();
        static $dl_properties = array();
        $this->_set_odt_styles_new($renderer, $style_data, $dl_properties);

        list ($tag, $state, $match) = $data;

        switch ( $state ) {
            case DOKU_LEXER_ENTER:
                if ($tag == 'dl') {
                    $properties = array();
                    $renderer->_odtTableOpenUseProperties($dl_properties);

                    $properties ['width'] = $style_data ['margin-left'];
                    $renderer->_odtTableAddColumnUseProperties($properties);
                } else {
                    if ($tag == 'dt') {
                        $renderer->tablerow_open();

                        $properties = array();
                        $properties ['border-left'] = 'none';
                        $properties ['border-right'] = 'none';
                        $properties ['border-top'] = 'none';
                        $properties ['border-bottom'] = $style_data ['border-bottom'];
                        $renderer->_odtTableCellOpenUseProperties ($properties);

                        $renderer->_odtSpanOpen('Plugin_DefinitionList_Term');
                    } else {
                        $properties = array();
                        $properties ['border-left'] = 'none';
                        $properties ['border-right'] = 'none';
                        $properties ['border-top'] = 'none';
                        $properties ['border-bottom'] = $style_data ['border-bottom'];
                        $renderer->_odtTableCellOpenUseProperties ($properties);

                        if (!empty($style_data ['image'])) {
                            $properties = array();
                            $properties ['margin-right'] = $style_data ['padding-left'];
                            $renderer->_odtAddImageUseProperties($style_data ['image'], $properties);
                        }

                        $renderer->_odtSpanOpen('Plugin_DefinitionList_Description');
                    }
                }
                break;
            case DOKU_LEXER_MATCHED:
            case DOKU_LEXER_UNMATCHED:                            // defensive, shouldn't occur
                $renderer->cdata($match);
                break;
            case DOKU_LEXER_EXIT:
                if ($tag != 'dl') {
                    $renderer->_odtSpanClose();
                    $renderer->tablecell_close();
                    if ($tag == 'dd') {
                        $renderer->p_close();
                        $renderer->tablerow_close();
                    }
                } else {
                    $renderer->table_close();
                }
                break;
        }

        return true;
    }

    /**
     * set definition list styles, used by render_odt_old()
     *
     * add definition list styles to the renderer's autostyles property (once only)
     *
     * @param  $renderer    current (odt) renderer object
     * @return void
     */
    protected function _set_odt_styles_old(Doku_Renderer $renderer) {
        static $do_once = true;

        if ($do_once) {
            $renderer->autostyles["def_f5_term"] = '
                <style:style style:name="def_f5_term" style:display-name="def_term" style:family="paragraph">
                    <style:paragraph-properties fo:margin-top="0.18cm" fo:margin-bottom="0cm" fo:keep-together="always" style:page-number="auto" fo:keep-with-next="always"/>
                    <style:text-properties fo:font-weight="bold"/>
                </style:style>';
            $renderer->autostyles["def_f5_list"] = '
                <style:style style:name="def_f5_list" style:display-name="def_list" style:family="paragraph">
                    <style:paragraph-properties fo:margin-left="0.25cm" fo:margin-right="0cm" fo:text-indent="0cm" style:auto-text-indent="false"/>
                </style:style>';

            $do_once = false;
        }
    }

    /**
     * Create definition list styles, used by render_odt_new():
     * Adds definition list styles to the ODT documents common styles (once only)
     *
     * @param  Doku_renderer $renderer   current (odt) renderer object
     * @param  Array         $style_data Array for returning relevant properties to the caller
     * @author: LarsDW223
     */
    protected function _set_odt_styles_new(Doku_Renderer $renderer, &$style_data, &$dl_properties) {
        static $do_once = true;

        if ($do_once) {
            // Create parent style to group the others beneath it
            if (!$renderer->styleExists('Plugin_DivAlign2')) {
                $parent_properties = array();
                $parent_properties ['style-parent'] = NULL;
                $parent_properties ['style-class'] = 'Plugin_DefinitionList';
                $parent_properties ['style-name'] = 'Plugin_DefinitionList';
                $parent_properties ['style-display-name'] = 'Plugin DefinitionList';
                $renderer->createTextStyle($parent_properties);
            }

            // Get the current HTML stack from the ODT renderer
            $stack = $renderer->getHTMLStack ();

            // Save state to restore it later
            $state = array();
            $stack->getState ($state);
            // Only for debugging ==> see end of this function
            //$renderer->dumpHTMLStack ();

            $stack->open('dl', 'class="plugin_definitionlist"', NULL, NULL);
            $renderer->getODTPropertiesFromElement ($dl_properties, $stack->getCurrentElement(), 'screen', true);

            $stack->open('dd', NULL, NULL, NULL);

            // Get CSS properties for ODT export.
            $dd_properties = array ();
            $renderer->getODTPropertiesFromElement ($dd_properties, $stack->getCurrentElement(), 'screen', true);

            $stack->close('dd');
            $stack->open('dt', NULL, NULL, NULL);

            // Get CSS properties for ODT export.
            $dt_properties = array ();
            $renderer->getODTPropertiesFromElement ($dt_properties, $stack->getCurrentElement(), 'screen', true);

            // Set style data to be returned to caller
            $style_data ['border-bottom'] = $dt_properties ['border-top'];
            $style_data ['image'] = $dd_properties ['background-image'];
            $style_data ['margin-left'] = $dd_properties ['margin-left'];
            $style_data ['padding-left'] = $dd_properties ['padding-left'];

            // Create text style for term
            $dt_properties ['border-top'] = NULL;
            $dt_properties ['style-class'] = NULL;
            $dt_properties ['style-parent'] = 'Plugin_DefinitionList';
            $dt_properties ['style-name'] = 'Plugin_DefinitionList_Term';
            $dt_properties ['style-display-name'] = 'Term';
            $renderer->createTextStyle($dt_properties);

            // Create text style for description
            $dd_properties ['border-bottom'] = $dt_properties ['border-top'];
            $dd_properties ['style-class'] = NULL;
            $dd_properties ['style-parent'] = 'Plugin_DefinitionList';
            $dd_properties ['style-name'] = 'Plugin_DefinitionList_Description';
            $dd_properties ['style-display-name'] = 'Description';
            $dd_properties ['background'] = NULL;
            $renderer->createTextStyle($dd_properties);

            $stack->restoreState ($state);
            // Only for debugging to check if the ODT plugins HTML stack
            // is restored to the start state
            //$renderer->dumpHTMLStack ();

            $do_once = false;
        }
    }

    /**
     * open a definition list tag, used by render_xhtml()
     *
     * @param   $tag  (string)    'dl', 'dt' or 'dd'
     * @return  (string)          html used to open the tag
     */
    protected function _open($tag) {
        if ($tag == 'dl') {
            if ($this->getConf('classname')) {
                $tag .= ' class="'.$this->getConf('classname').'"';
            }
            $wrap = NL;
        } else {
            $wrap = ($tag == 'dt' && $this->getConf('dt_fancy')) ? '<span class="term">' : '';
        }
        return "<$tag>$wrap";
    }

    /**
     * close a definition list tag, used by render_xhtml()
     *
     * @param   $tag  (string)    'dl', 'dt' or 'dd'
     * @return  (string)          html used to close the tag
     */
    protected function _close($tag) {
        $wrap = ($tag == 'dt' && $this->getConf('dt_fancy')) ? '</span>' : '';
        return "$wrap</$tag>\n";
    }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
