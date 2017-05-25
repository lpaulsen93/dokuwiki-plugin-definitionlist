<?php
/**
 * @group plugin_definitionlist
 */
class plugin_definitionlist_syntax_test extends DokuWikiTest {

    protected $pluginsEnabled = array('definitionlist');
    protected $renderer = null;

    function setUp() {
        parent::setUp();

        $this->renderer = new Doku_Renderer_xhtml();
    }

    protected function render($rawwiki,$format='xhtml') {
        $instructions = p_get_instructions($rawwiki);
        return p_render($format, $instructions, $info);
    }

    function test_basic_singleline() {
        $in = NL.'  ; Term : Definition'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd>Definition</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_basic_multiline() {
        $in = NL.'  ; Term'.NL
                .'  : Definition'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Definition</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_multiple_definitions() {
        $in = NL.'  ; Term'.NL
                .'  : Definition one'.NL
                .'  : Definition two'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Definition one</dd>'.NL
                   .'<dd> Definition two</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_newline_in_definition() {
        $in = NL.'  ; Term'.NL
                .'  : Definition one'.NL
                .'continues'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Definition one'.NL
                   .'continues</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_newline_in_definition_with_following_para() {
        $in = NL.'  ; Term'.NL
                .'  : Definition one'.NL
                .'continues'.NL
                .NL
                .'Then new paragraph.'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Definition one'.NL
                   .'continues</dd>'.NL
                   .'</dl>'.NL
                   .NL
                   .'<p>'.NL
                   .'Then new paragraph.'.NL
                   .'</p>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_basic_with_following_preformatted() {
        $in = NL.'  ; Term'.NL
                .'  : Definition'.NL
                .NL
                .'  Preformatted'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Definition</dd>'.NL
                   .'</dl>'.NL
                   .'<pre class="code">Preformatted</pre>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_nonfancy() {
        global $conf;
        $conf['plugin']['definitionlist']['dt_fancy'] = 0;

        $in1 = NL.'  ; Term'.NL
                 .'  : Definition'.NL;
        $in2 = NL.'  ; Term : Definition'.NL;
        $expected1 = '<dl class="plugin_definitionlist">'.NL
                   .'<dt> Term</dt>'.NL
                   .'<dd> Definition</dd>'.NL
                   .'</dl>'.NL;
        $expected2 = '<dl class="plugin_definitionlist">'.NL
                   .'<dt> Term</dt>'.NL
                   .'<dd>Definition</dd>'.NL
                   .'</dl>'.NL;


        $this->assertEquals($expected1, $this->render($in1));
        $this->assertEquals($expected2, $this->render($in2));
    }

    function test_custom_class_name() {
        global $conf;
        $conf['plugin']['definitionlist']['classname'] = 'foo';

        $in = NL.'  ; Term'.NL
                .'  : Definition'.NL;
        $expected = '<dl class="foo">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Definition</dd>'.NL
                   .'</dl>'.NL;


        $this->assertEquals($expected, $this->render($in));
    }

    function test_two_dlists_with_blank_line_between() {
        $in = NL.'  ; Term : Definition'.NL
             .NL.'  ; Another term : Def'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd>Definition</dd>'.NL
                   .'</dl>'.NL
                   .'<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Another term</span></dt>'.NL
                   .'<dd>Def</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_dd_with_ul() {
        $in = NL.'  ; Term'.NL
                .'  : Some parts:'.NL
                .'  * Part 1'.NL
                .'  * Part 2'.NL
                .NL
                .'  ; Term 2'.NL
                .'  : Def'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Some parts:<ul>'.NL
                   .'<li class="level1"><div class="li"> Part 1</div>'.NL
                   .'</li>'.NL
                   .'<li class="level1"><div class="li"> Part 2</div>'.NL
                   .'</li>'.NL
                   .'</ul>'.NL
                   .'</dd>'.NL
                   .'<dt><span class="term"> Term 2</span></dt>'.NL
                   .'<dd> Def</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_dd_with_ul_followed_by_ordered_list() {
        $in = NL.'  ; Term'.NL
                .'  : Some parts:'.NL
                .'  * Part 1'.NL
                .'  * Part 2'.NL
                .NL
                .NL
                .'  - Item'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Some parts:<ul>'.NL
                   .'<li class="level1"><div class="li"> Part 1</div>'.NL
                   .'</li>'.NL
                   .'<li class="level1"><div class="li"> Part 2</div>'.NL
                   .'</li>'.NL
                   .'</ul>'.NL
                   .'</dd>'.NL
                   .'</dl>'.NL
                   .'<ol>'.NL
                   .'<li class="level1"><div class="li"> Item</div>'.NL
                   .'</li>'.NL
                   .'</ol>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

    function test_dd_with_ul_followed_by_2nd_dl() {
        $in = NL.'  ; Term'.NL
                .'  : Some parts:'.NL
                .'  * Part 1'.NL
                .'  * Part 2'.NL
                .NL
                .NL
                .'  ; Term 2'.NL
                .'  : Def'.NL;
        $expected = '<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term</span></dt>'.NL
                   .'<dd> Some parts:<ul>'.NL
                   .'<li class="level1"><div class="li"> Part 1</div>'.NL
                   .'</li>'.NL
                   .'<li class="level1"><div class="li"> Part 2</div>'.NL
                   .'</li>'.NL
                   .'</ul>'.NL
                   .'</dd>'.NL
                   .'</dl>'.NL
                   .'<dl class="plugin_definitionlist">'.NL
                   .'<dt><span class="term"> Term 2</span></dt>'.NL
                   .'<dd> Def</dd>'.NL
                   .'</dl>'.NL;

        $this->assertEquals($expected, $this->render($in));
    }

}
