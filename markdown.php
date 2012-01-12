<?php
/**
 * MarkDown for PHP
 *
 * @license AOL <aol.nexua.org>
 * @copyright (c) 2012 Nijikokun <nexua.org>
 *
 * @author 2012 Nijikokun <nijikokun@gmail.com> @nijikokun
 * @author 2010 Sean Sandy <http://seanja.com/>
 * @author 2009 Michel Fortin <http://michelf.com/>
 * 
 * @attribute Based on MarkDown (c) 2003-2006 John Gruber <http://daringfireball.net/>
 */

/**
 * Change to ">" for HTML output
 * @var string
 */
define('MARKDOWN_EMPTY_ELEMENT_SUFFIX', " />");

/**
 * Define the width of a tab for code blocks.
 * @var int
 */
define('MARKDOWN_TAB_WIDTH', 4);


/**
 * Optional title attribute for footnote links
 * @var string
 */
define('MARKDOWN_FN_LINK_TITLE', "");

/**
 * Optional title attribute for backlinks
 * @var string
 */
define('MARKDOWN_FN_BACKLINK_TITLE', "");


// Optional class attribute for footnote links and backlinks.
/**
 * Optional class attribute for footnote links
 * @var string
 */
define('MARKDOWN_FN_LINK_CLASS', "");

/**
 * Optional class attribute for backlinks
 * @var string
 */
define('MARKDOWN_FN_BACKLINK_CLASS', "");

class Markdown {
	/**
	 * Needed to insert a maximum bracked depth while converting to PHP.
	 * @var int
	 */
	protected $nested_brackets_depth = 6;

	/**
	 * Regex to match balanced [brackets].
	 * @var string
	 */
	protected $nested_brackets_re;

	/**
	 * Maximum nested parenthesis depth
	 * @var int
	 */
	protected $nested_url_parenthesis_depth = 4;

	/**
	 * Nested parenthesis regex
	 * @var string
	 */
	protected $nested_url_parenthesis_re;

	/**
	 * Table of hash values for escaped characters:
	 * @var string
	 */
	protected $escape_chars = '\`*_{}[]()>#+-.!';

	/**
	 * Escape characters regex
	 * @var string
	 */
	protected $escape_chars_re;

	/**
	 * If the element is an empty element, add this to it
	 * @var string
	 */
	protected $empty_element_suffix = MARKDOWN_EMPTY_ELEMENT_SUFFIX;

	/**
	 * The width of a tab for code blocks.
	 * @var int
	 */
	protected $tab_width = MARKDOWN_TAB_WIDTH;

	/**
	 * Change to `true` to disallow markup
	 * @var boolean
	 */
	protected $no_markup = false;

	/**
	 * Change to `true` to disallow entities
	 * @var boolean
	 */
	protected $no_entities = false;

	/**
	 * Predefined urls for reference links and images
	 * @var array
	 */
	protected $predef_urls = array();

	/**
	 * Predefined titles for reference links and images
	 * @var array
	 */
	protected $predef_titles = array();

	/**
	 *
	 * @var array
	 */
	protected $urls = array();

	/**
	 *
	 * @var array
	 */
	protected $titles = array();

	/**
	 *
	 * @var array
	 */
	protected $html_hashes = array();

	/**
	 * Status flag to avoid invalid nesting.
	 * @var boolean
	 */
	protected $in_anchor = false;

	/**
	 *
	 * @var array
	 */
	protected $document_gamut = array(
		// Strip link definitions, store in hashes.
		"stripLinkDefinitions" => 20,
		"runBasicBlockGamut" => 30,
	);

	/**
	 *
	 * @var array
	 */
	protected $block_gamut = array(
		#
		// These are all the transformations that form block-level
		// tags like paragraphs, headers, and list items.
		#
		"doHeaders" => 10,
		"doHorizontalRules" => 20,
		"doLists" => 40,
		"doCodeBlocks" => 50,
		"doBlockQuotes" => 60,
	);

	/**
	 * These are all the transformations that occur *within* block-level tags like paragraphs, headers, and list items.
	 * @var array
	 */
	protected $span_gamut = array(
		//Process character escapes, code spans, and inline HTML
		//in one shot.
		"parseSpan" => -30,
		//Process anchor and image tags. Images must come first,
		//because ![foo][f] looks like an anchor.
		"doImages" => 10,
		"doAnchors" => 20,
		//Make links out of things like `<http://example.com/>`
		//Must come after doAnchors, because you can use < and >
		//delimiters in inline links like [this](<url>).
		"doAutoLinks" => 30,
		"encodeAmpsAndAngles" => 40,
		"doItalicsAndBold" => 50,
		"doHardBreaks" => 60,
	);

	/**
	 * The depth of the current list
	 * @var int
	 */
	protected $list_level = 0;

	/**
	 *
	 * @var array
	 */
	protected $em_relist = array(
		'' => '(?:(?<!\*)\*(?!\*)|(?<!_)_(?!_))(?=\S|$)(?![.,:;]\s)',
		'*' => '(?<=\S|^)(?<!\*)\*(?!\*)',
		'_' => '(?<=\S|^)(?<!_)_(?!_)',
	);

	/**
	 *
	 * @var array
	 */
	protected $strong_relist = array(
		'' => '(?:(?<!\*)\*\*(?!\*)|(?<!_)__(?!_))(?=\S|$)(?![.,:;]\s)',
		'**' => '(?<=\S|^)(?<!\*)\*\*(?!\*)',
		'__' => '(?<=\S|^)(?<!_)__(?!_)',
	);

	/**
	 *
	 * @var array
	 */
	protected $em_strong_relist = array(
		'' => '(?:(?<!\*)\*\*\*(?!\*)|(?<!_)___(?!_))(?=\S|$)(?![.,:;]\s)',
		'***' => '(?<=\S|^)(?<!\*)\*\*\*(?!\*)',
		'___' => '(?<=\S|^)(?<!_)___(?!_)',
	);

	/**
	 *
	 * @var string
	 */
	protected $em_strong_prepared_relist;
	/**
	 *
	 * @var string
	 */
	protected $utf8_strlen = 'mb_strlen';

	/**
	 * Constructor function. Initialize appropriate member variables.
	 */
	function __construct() {
		$this->initDetab();
		$this->prepareItalicsAndBold();

		$this->nested_brackets_re =
				str_repeat('(?>[^\[\]]+|\[', $this->nested_brackets_depth) .
				str_repeat('\])*', $this->nested_brackets_depth);

		$this->nested_url_parenthesis_re =
				str_repeat('(?>[^()\s]+|\(', $this->nested_url_parenthesis_depth) .
				str_repeat('(?>\)))*', $this->nested_url_parenthesis_depth);

		$this->escape_chars_re = '[' . preg_quote($this->escape_chars) . ']';

		// Sort document, block, and span gamut in ascendent priority order.
		asort($this->document_gamut);
		asort($this->block_gamut);
		asort($this->span_gamut);
	}

	public function __invoke($text) {
		return $this->transform($text);
	}

	/**
	 * Called before the transformation process starts to setup parser starts
	 */
	protected function setup() {
		$this->urls = $this->predef_urls;
		$this->titles = $this->predef_titles;
		$this->html_hashes = array();
		$in_anchor = false;
	}

	/**
	 * Called after the transformation process to clear any variable which may be taking up memory unnecessarly.
	 */
	protected function teardown() {
		$this->urls = array();
		$this->titles = array();
		$this->html_hashes = array();
	}

	/**
	 * Main function. Performs some preprocessing on the input text and pass it through the document gamut.
	 * @param string $text
	 * @return string
	 */
	public function transform($text) {
		$this->setup();

		// Remove UTF-8 BOM and marker character in input, if present.
		$text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

		// Standardize line endings:
		//   DOS to Unix and Mac to Unix
		$text = preg_replace('{\r\n?}', "\n", $text);

		// Make sure $text ends with a couple of newlines:
		$text .= "\n\n";

		// Convert all tabs to spaces.
		$text = $this->detab($text);

		// Turn block-level HTML blocks into hash entries
		$text = $this->hashHTMLBlocks($text);

		// Strip any lines consisting only of spaces and tabs.
		// This makes subsequent regexen easier to write, because we can
		// match consecutive blank lines with /\n+/ instead of something
		// contorted like /[ ]*\n+/ .
		$text = preg_replace('/^[ ]+$/m', '', $text);

		// Run document gamut methods.
		foreach ($this->document_gamut as $method => $priority)
			$text = $this->$method($text);

		$this->teardown();
		return $text . PHP_EOL;
	}

	/**
	 * Strips link definitions from text, stores the URLs and titles in hash references
	 * @param string $text
	 * @return string
	 */
	protected function stripLinkDefinitions($text) {
		$less_than_tab = $this->tab_width - 1;

		// Link defs are in the form: ^[id]: url "optional title"
		$text = preg_replace_callback(
			'{^[ ]'
              .'{0,'.$less_than_tab.'} \[(.+)\][ ]?: ' // id = $1
			  .'[ ]*\n?[ ]*' // maybe *one* newline
			  .'(?:<(.+?)>|(\S+?))' // URL = $2 & $3
              .'[ ]*\n?[ ]*' // maybe *one* newline
			  .'(?:(?<=\s)'
              .'["(\']'
			  .'(.*?)' //optional title = $4
              .'[\')"]'
			  .'[ ]*'
			  .')?'
     		.'(?:\n+|\Z)}xm',
			array(&$this, 'stripLinkDefinitions_callback'),
			$text
		);

		return $text;
	}

	/**
	 *
	 * @param array $matches
	 * @return string
	 */
	protected function stripLinkDefinitions_callback($matches) {
		$link_id = strtolower($matches[1]);
		$url = $matches[2] == '' ? $matches[3] : $matches[2];
		$this->urls[$link_id] = $url;
		$this->titles[$link_id] = & $matches[4];
		return ''; // String that will replace the block
	}

	/**
	 * Hashify HTML blocks:
	 * We only want to do this for block-level HTML tags, such as headers,
	 * lists, and tables. That's because we still want to wrap <p>s around
	 * "paragraphs" that are wrapped in non-block-level tags, such as anchors,
	 * phrase emphasis, and spans. The list of tags we're looking for is
	 * hard-coded:
	 * 	*	List "a" is made of tags which can be both inline or block-level.
	 * 		These will be treated block-level when the start tag is alone on
	 * 		its line, otherwise they're not matched here and will be taken as
	 * 		inline later.
	 * 	*	List "b" is made of tags which are always block-level;
	 * @param string $text
	 * @return string
	 */
	protected function hashHTMLBlocks($text) {
		if ($this->no_markup)
			return $text;

		$less_than_tab = $this->tab_width - 1;
		$block_tags_a_re = 'ins|del';
		$block_tags_b_re = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|script|noscript|form|fieldset|iframe|math';

		// Regular expression for the content of a block tag.
		$nested_tags_level = 4;

		$attr = '
			(?>				// optional tag attributes
			  \s			// starts with whitespace
			  (?>
				[^>"/]+		// text outside quotes
			  |
				/+(?!>)		// slash not followed by ">"
			  |
				"[^"]*"		// text inside double quotes (tolerate ">")
			  |
				\'[^\']*\'	// text inside single quotes (tolerate ">")
			  )*
			)?
		';

		$content = str_repeat(
			'(?>
			  [^<]+			// content without tag
			|
			  <\2			// nested opening tag
				' . $attr . '	// attributes
				(?>
				  />
				|
				  >', $nested_tags_level) . // end of opening tag
			'.*?' . // last level nested tag content
			str_repeat('
				  </\2\s*>	// closing nested tag
				)
			  |
				<(?!/\2\s*>	// other tags with a different name
			  )
			)*',
			$nested_tags_level
		);

		$content2 = str_replace('\2', '\3', $content);

		// First, look for nested blocks, e.g.:
		// 	<div>
		// 		<div>
		// 		tags for inner block must be indented.
		// 		</div>
		// 	</div>
		//
		// The outermost tags must start at the left margin for this to match, and
		// the inner nested divs must be indented.
		// We need to do this before the next, more liberal match, because the next
		// match will start at the first `<div>` and stop at the first `</div>`.
		$text = preg_replace_callback(
			'{(?>
			(?>
				(?<=\n\n)
				|
				\A\n?
			)
			(
						[ ]{0,' . $less_than_tab . '}
						<(' . $block_tags_b_re . ')
						' . $attr . '>
						' . $content . '
						</\2>
						[ ]*
						(?=\n+|\Z)

			|

						[ ]{0,' . $less_than_tab . '}
						<(' . $block_tags_a_re . ')
						' . $attr . '>[ ]*\n
						' . $content2 . '
						</\3>
						[ ]*
						(?=\n+|\Z)

			|
						[ ]{0,' . $less_than_tab . '}
						<(hr)
						' . $attr . '
						/?>
						[ ]*
						(?=\n{2,}|\Z)

			|

					[ ]{0,' . $less_than_tab . '}
					(?s:
						<!-- .*? -->
					)
					[ ]*
					(?=\n{2,}|\Z)

			|

					[ ]{0,' . $less_than_tab . '}
					(?s:
						<([?%])
						.*?
						\2>
					)
					[ ]*
					(?=\n{2,}|\Z)

			)
			)}Sxmi',
			array(&$this, 'hashHTMLBlocks_callback'),
			$text
		);

		return $text;
	}

	protected function hashHTMLBlocks_callback($matches) {
		$text = $matches[1];
		$key = $this->hashBlock($text);
		return "\n\n$key\n\n";
	}

	// Called whenever a tag must be hashed when a protected function insert an atomic
	// element in the text stream. Passing $text to through this protected function gives
	// a unique text-token which will be reverted back when calling unhash.
	// The $boundary argument specify what character should be used to surround
	// the token. By convension, "B" is used for block elements that needs not
	// to be wrapped into paragraph tags at the end, ":" is used for elements
	// that are word separators and "X" is used in the general case.
	// Swap back any tag hash found in $text so we do not have to `unhash`
	// multiple times at the end.
	protected function hashPart($text, $boundary = 'X') {
		$text = $this->unhash($text);

		// Then hash the block.
		static $i = 0;
		$key = "$boundary\x1A" . ++$i . $boundary;
		$this->html_hashes[$key] = $text;
		return $key; // String that will replace the tag.
	}

	// Shortcut protected function for hashPart with block-level boundaries.
	protected function hashBlock($text) {
		return $this->hashPart($text, 'B');
	}

  /**
   * Run block gamut tranformations.
   * We need to escape raw HTML in Markdown source before doing anything
   * else. This need to be done for each block, and not only at the
   * begining in the Markdown protected function since hashed blocks can be part of
   * list items and could have been indented. Indented blocks would have
   * been seen as a code block in a previous pass of hashHTMLBlocks.
   * @param string $text
   * @return string escaped html
   */
	protected function runBlockGamut($text) {
		$text = $this->hashHTMLBlocks($text);
		return $this->runBasicBlockGamut($text);
	}

  /**
   * Run block gamut tranformations, without hashing HTML blocks. This is
   * useful when HTML blocks are known to be already hashed, like in the first
   * whole-document pass.
   * @param string $text
   * @return string The converted text
   */
	protected function runBasicBlockGamut($text) {
		foreach ($this->block_gamut as $method => $priority) 
			$text = $this->$method($text);
		
		// Finally form paragraph and restore hashed blocks.
		$text = $this->formParagraphs($text);

		return $text;
	}

	protected function doHorizontalRules($text) {
		// Do Horizontal Rules:
		return preg_replace(
			'{
				^[ ]{0,3}
				([-*_])
				(?>
					[ ]{0,2}
					\1
				){2,}
				[ ]*
				$
			}mx',
			"\n" . $this->hashBlock("<hr$this->empty_element_suffix") . "\n",
			$text
		);
	}

	protected function runSpanGamut($text) {
		foreach ($this->span_gamut as $method => $priority)
			$text = $this->$method($text);

		return $text;
	}

	protected function doHardBreaks($text) {
		return preg_replace_callback(
			'/ {2,}\n/',
			array(&$this, 'doHardBreaks_callback'), 
			$text
		);
	}

	protected function doHardBreaks_callback($matches) {
		return $this->hashPart("<br$this->empty_element_suffix\n");
	}

	protected function doAnchors($text) {
		// Turn Markdown link shortcuts into XHTML <a> tags.
		if ($this->in_anchor)
			return $text;

		$this->in_anchor = true;

		// First, handle reference-style links: [link text] [id]
		$text = preg_replace_callback(
			'{
			(
			  \[
				(' . $this->nested_brackets_re . ')
			  \]

			  [ ]?
			  (?:\n[ ]*)?

			  \[
				(.*?)
			  \]
			)
			}xs',
			array(&$this, 'doAnchors_reference_callback'), 
			$text
		);

		// Next, inline-style links: [link text](url "optional title")
		$text = preg_replace_callback(
			'{
			(
			  \[
				(' . $this->nested_brackets_re . ')
			  \]
			  \(
				[ \n]*
				(?:
					<(.+?)>	// href = $3
				|
					(' . $this->nested_url_parenthesis_re . ')
				)
				[ \n]*
				(
				  ([\'"])
				  (.*?)
				  \6
				  [ \n]*
				)?
			  \)
			)
			}xs',
			array(&$this, 'doAnchors_inline_callback'), 
			$text
		);


		// Last, handle reference-style shortcuts: [link text]
		// These must come last in case you've also got [link text][1]
		// or [link text](/foo)
		$text = preg_replace_callback(
			'{
			(
			  \[
				([^\[\]]+)
			  \]
			)
			}xs',
			array(&$this, 'doAnchors_reference_callback'), 
			$text
		);

		$this->in_anchor = false;
		return $text;
	}

	protected function doAnchors_reference_callback($matches) {
		$whole_match = $matches[1];
		$link_text = $matches[2];
		$link_id = & $matches[3];

		if ($link_id == "")
			$link_id = $link_text; // for shortcut links like [this][] or [this].

		// lower-case and turn embedded newlines into spaces
		$link_id = strtolower($link_id);
		$link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

		if (isset($this->urls[$link_id])) {
			$url = $this->urls[$link_id];
			$url = $this->encodeAttribute($url);
			$result = "<a href=\"$url\"";

			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .= " title=\"$title\"";
			}

			$link_text = $this->runSpanGamut($link_text);
			$result .= ">$link_text</a>";
			$result = $this->hashPart($result);
		} else 
			$result = $whole_match;

		return $result;
	}

	protected function doAnchors_inline_callback($matches) {
		$whole_match = $matches[1];
		$link_text = $this->runSpanGamut($matches[2]);
		$url = $matches[3] == '' ? $matches[4] : $matches[3];
		$title = & $matches[7];
		$url = $this->encodeAttribute($url);
		$result = "<a href=\"$url\"";

		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .= " title=\"$title\"";
		}

		$link_text = $this->runSpanGamut($link_text);
		$result .= ">$link_text</a>";
		return $this->hashPart($result);
	}

	protected function doImages($text) {
		// Turn Markdown image shortcuts into <img> tags.
		// First, handle reference-style labeled images: ![alt text][id]
		$text = preg_replace_callback(
			'{
			(
			  !\[
				(' . $this->nested_brackets_re . ')
			  \]

			  [ ]?
			  (?:\n[ ]*)?

			  \[
				(.*?)
			  \]

			)
			}xs',
			array(&$this, 'doImages_reference_callback'), 
			$text
		);

		// Next, handle inline images:  ![alt text](url "optional title")
		// Don't forget: encode * and _
		$text = preg_replace_callback(
			'{
			(
			  !\[
				(' . $this->nested_brackets_re . ')
			  \]
			  \s?
			  \(
				[ \n]*
				(?:
					<(\S*)>
				|
					(' . $this->nested_url_parenthesis_re . ')\
				)
				[ \n]*
				(
				  ([\'"])
				  (.*?)
				  \6
				  [ \n]*
				)?
			  \)
			)
			}xs',
			array(&$this, 'doImages_inline_callback'), 
			$text
		);

		return $text;
	}

	protected function doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text = $matches[2];
		$link_id = strtolower($matches[3]);

		if ($link_id == "")
			$link_id = strtolower($alt_text); // for shortcut links like ![this][].
		
		$alt_text = $this->encodeAttribute($alt_text);

		if (isset($this->urls[$link_id])) {
			$url = $this->encodeAttribute($this->urls[$link_id]);
			$result = "<img src=\"$url\" alt=\"$alt_text\"";

			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .= " title=\"$title\"";
			}

			$result .= $this->empty_element_suffix;
			$result = $this->hashPart($result);
		} else 
			$result = $whole_match; // If there's no such link ID, leave intact

		return $result;
	}

	protected function doImages_inline_callback($matches) {
		$whole_match = $matches[1];
		$alt_text = $matches[2];
		$url = $matches[3] == '' ? $matches[4] : $matches[3];
		$title = & $matches[7];
		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";

		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .= " title=\"$title\""; // $title already quoted
		}

		$result .= $this->empty_element_suffix;
		return $this->hashPart($result);
	}

	protected function doHeaders($text) {
		// Setext-style headers:
		//	  Header 1
		//	  ========
		//
		//	  Header 2
		//	  --------
		$text = preg_replace_callback(
			'{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx',
			array(&$this, 'doHeaders_callback_setext'), 
			$text
		);

		// atx-style headers:
		//	# Header 1
		//	## Header 2
		//	## Header 2 with closing hashes ##
		//	...
		//	###### Header 6
		$text = preg_replace_callback(
			'{
				^(\#{1,6})
				[ ]*
				(.+?)
				[ ]*
				\#*
				\n+
			}xm',
			array(&$this, 'doHeaders_callback_atx'), 
			$text
		);

		return $text;
	}

	protected function doHeaders_callback_setext($matches) {
		// Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1]))
			return $matches[0];

		$level = $matches[2]{0} == '=' ? 1 : 2;
		$block = "<h$level>" . $this->runSpanGamut($matches[1]) . "</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	protected function doHeaders_callback_atx($matches) {
		$level = strlen($matches[1]);
		$block = "<h$level>" . $this->runSpanGamut($matches[2]) . "</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	protected function doLists($text) {
		// Form HTML ordered (numbered) and unordered (bulleted) lists.
		$less_than_tab = $this->tab_width - 1;

		// Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re = '[*+-]';
		$marker_ol_re = '\d+[.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";

		$markers_relist = array(
			$marker_ul_re => $marker_ol_re,
			$marker_ol_re => $marker_ul_re,
		);

		foreach ($markers_relist as $marker_re => $other_marker_re) {
			// Re-usable pattern to match any entirel ul or ol list:
			$whole_list_re = '
				(
				  (
					([ ]{0,' . $less_than_tab . '})
					(' . $marker_re . ')
					[ ]+
				  )
				  (?s:.+?)
				  (
					  \z
					|
					  \n{2,}
					  (?=\S)
					  (?!
						[ ]*
						' . $marker_re . '[ ]+
					  )
					|
					  (?=
					    \n
						\3
						' . $other_marker_re . '[ ]+
					  )
				  )
				)
			';

			// We use a different prefix before nested lists than top-level lists.
			// See extended comment in _ProcessListItems().
			if ($this->list_level) {
				$text = preg_replace_callback(
					'{
						^
						' . $whole_list_re . '
					}mx',
					array(&$this, 'doLists_callback'), 
					$text
				);
			} else {
				$text = preg_replace_callback(
					'{
						(?:(?<=\n)\n|\A\n?)
						' . $whole_list_re . '
					}mx',
					array(&$this, 'doLists_callback'), 
					$text
				);
			}
		}

		return $text;
	}

	protected function doLists_callback($matches) {
		// Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re = '[*+-]';
		$marker_ol_re = '\d+[.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";

		$list = $matches[1];
		$list_type = preg_match("/$marker_ul_re/", $matches[4]) ? "ul" : "ol";
		$marker_any_re = ( $list_type == "ul" ? $marker_ul_re : $marker_ol_re );

		$list .= "\n";
		$result = $this->processListItems($list, $marker_any_re);
		$result = $this->hashBlock("<$list_type>\n" . $result . "</$list_type>");
		return "\n" . $result . "\n\n";
	}

	protected function processListItems($list_str, $marker_any_re) {
		// Process the contents of a single ordered or unordered list, splitting it
		// into individual list items.
		//
		// The $this->list_level global keeps track of when we're inside a list.
		// Each time we enter a list, we increment it; when we leave a list,
		// we decrement. If it's zero, we're not in a list anymore.
		//
		// We do this because when we're not inside a list, we want to treat
		// something like this:
		//
		//		I recommend upgrading to version
		//		8. Oops, now this line is treated
		//		as a sub-list.
		//
		// As a single paragraph, despite the fact that the second line starts
		// with a digit-period-space sequence.
		//
		// Whereas when we're inside a list (or sub-list), that line will be
		// treated as the start of a sub-list. What a kludge, huh? This is
		// an aspect of Markdown's syntax that's hard to parse perfectly
		// without resorting to mind-reading. Perhaps the solution is to
		// change the syntax rules such that sub-lists must start with a
		// starting cardinal number; e.g. "1." or "a.".
		$this->list_level++;

		// trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);
		$list_str = preg_replace_callback(
			'{
			(\n)?
			(^[ ]*)
			(' . $marker_any_re . '
				(?:[ ]+|(?=\n))
			)
			((?s:.*?))
			(?:(\n+(?=\n))|\n)
			(?= \n* (\z | \2 (' . $marker_any_re . ') (?:[ ]+|(?=\n))))
			}xm',
			array(&$this, 'processListItems_callback'), 
			$list_str
		);

		$this->list_level--;
		return $list_str;
	}

	protected function processListItems_callback($matches) {
		$item = $matches[4];
		$leading_line = & $matches[1];
		$leading_space = & $matches[2];
		$marker_space = $matches[3];
		$tailing_blank_line = & $matches[5];

		if ($leading_line || $tailing_blank_line || preg_match('/\n{2,}/', $item)) {
			// Replace marker with the appropriate whitespace indentation
			$item = $leading_space . str_repeat(' ', strlen($marker_space)) . $item;
			$item = $this->runBlockGamut($this->outdent($item) . "\n");
		} else {
			// Recursion for sub-lists:
			$item = $this->doLists($this->outdent($item));
			$item = preg_replace('/\n+$/', '', $item);
			$item = $this->runSpanGamut($item);
		}

		return "<li>" . $item . "</li>\n";
	}

	protected function doCodeBlocks($text) {
		//	Process Markdown `<pre><code>` blocks.
		$text = preg_replace_callback(
			'{
				(?:\n\n|\A\n?)
				(
				  (?>
					[ ]{' . $this->tab_width . '}
					.*\n+
				  )+
				)
				((?=^[ ]{0,' . $this->tab_width . '}\S)|\Z)
			}xm',
			array(&$this, 'doCodeBlocks_callback'), 
			$text
		);

		return $text;
	}

	protected function doCodeBlocks_callback($matches) {
		$codeblock = $matches[1];
		$codeblock = $this->outdent($codeblock);
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		// trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);
		$codeblock = "<pre><code>$codeblock\n</code></pre>";
		return "\n\n" . $this->hashBlock($codeblock) . "\n\n";
	}

	protected function makeCodeSpan($code) {

		// Create a code span markup for $code. Called from handleSpanToken.
		$code = htmlspecialchars(trim($code), ENT_NOQUOTES);
		return $this->hashPart("<code>$code</code>");
	}

	protected function prepareItalicsAndBold() {

		// Prepare regular expressions for searching emphasis tokens in any context.
		foreach ($this->em_relist as $em => $em_re) {
			foreach ($this->strong_relist as $strong => $strong_re) {
				// Construct list of allowed token expressions.
				$token_relist = array();

				if (isset($this->em_strong_relist["$em$strong"]))
					$token_relist[] = $this->em_strong_relist["$em$strong"];

				$token_relist[] = $em_re;
				$token_relist[] = $strong_re;

				// Construct master expression from list.
				$token_re = '{(' . implode('|', $token_relist) . ')}';
				$this->em_strong_prepared_relist["$em$strong"] = $token_re;
			}
		}
	}

	protected function doItalicsAndBold($text) {
		$token_stack = array('');
		$text_stack = array('');
		$em = '';
		$strong = '';
		$tree_char_em = false;

		while (1) {
			// Get prepared regular expression for seraching emphasis tokens
			// in current context.
			$token_re = $this->em_strong_prepared_relist["$em$strong"];

			// Each loop iteration search for the next emphasis token.
			// Each token is then passed to handleSpanToken.
			$parts = preg_split($token_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
			$text_stack[0] .= $parts[0];
			$token = & $parts[1];
			$text = & $parts[2];

			if (empty($token)) {
				// Reached end of text span: empty stack without emitting.
				// any more emphasis.
				while ($token_stack[0]) {
					$text_stack[1] .= array_shift($token_stack);
					$text_stack[0] .= array_shift($text_stack);
				}

				break;
			}

			$token_len = strlen($token);
			if ($tree_char_em) {
				// Reached closing marker while inside a three-char emphasis.
				if ($token_len == 3) {
					// Three-char closing marker, close em and strong.
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong><em>$span</em></strong>";
					$text_stack[0] .= $this->hashPart($span);
					$em = '';
					$strong = '';
				} else {
					// Other closing marker: close one em or strong and
					// change current token state to match the other
					$token_stack[0] = str_repeat($token{0}, 3 - $token_len);
					$tag = $token_len == 2 ? "strong" : "em";
					$span = $text_stack[0];
					$span = $this->runSpanGamut($span);
					$span = "<$tag>$span</$tag>";
					$text_stack[0] = $this->hashPart($span);
					$$tag = ''; // $$tag stands for $em or $strong
				}
				$tree_char_em = false;
			} else if ($token_len == 3) {
				if ($em) {
					// Reached closing marker for both em and strong.
					// Closing strong marker:
					for ($i = 0; $i < 2; ++$i) {
						$shifted_token = array_shift($token_stack);
						$tag = strlen($shifted_token) == 2 ? "strong" : "em";
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<$tag>$span</$tag>";
						$text_stack[0] .= $this->hashPart($span);
						$$tag = ''; // $$tag stands for $em or $strong
					}
				} else {
					// Reached opening three-char emphasis marker. Push on token
					// stack; will be handled by the special condition above.
					$em = $token{0};
					$strong = "$em$em";
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$tree_char_em = true;
				}
			} else if ($token_len == 2) {
				if ($strong) {
					// Unwind any dangling emphasis marker:
					if (strlen($token_stack[0]) == 1) {
						$text_stack[1] .= array_shift($token_stack);
						$text_stack[0] .= array_shift($text_stack);
					}

					// Closing strong marker:
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong>$span</strong>";
					$text_stack[0] .= $this->hashPart($span);
					$strong = '';
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$strong = $token;
				}
			} else {
				// Here $token_len == 1
				if ($em) {
					if (strlen($token_stack[0]) == 1) {
						// Closing emphasis marker:
						array_shift($token_stack);

						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<em>$span</em>";
						$text_stack[0] .= $this->hashPart($span);
						$em = '';
					} else {
						$text_stack[0] .= $token;
					}
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$em = $token;
				}
			}
		}

		return $text_stack[0];
	}

	protected function doBlockQuotes($text) {
		$text = preg_replace_callback(
			'/
			  (
				(?>
				  ^[ ]*>[ ]?
					.+\n
				  (.+\n)*
				  \n*
				)+
			  )
			/xm',
			array(&$this, 'doBlockQuotes_callback'), 
			$text
		);

		return $text;
	}

	protected function doBlockQuotes_callback($matches) {
		$bq = $matches[1];

		// trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
		$bq = $this->runBlockGamut($bq);  // recurse
		$bq = preg_replace('/^/m', "  ", $bq);

		// These leading spaces cause problem with <pre> content,
		// so we need to fix that:
		$bq = preg_replace_callback(
			'{(\s*<pre>.+?</pre>)}sx',
			array(&$this, 'doBlockQuotes_callback2'), 
			$bq
		);

		return "\n" . $this->hashBlock("<blockquote>\n$bq\n</blockquote>") . "\n\n";
	}

	protected function doBlockQuotes_callback2($matches) {
		$pre = $matches[1];
		$pre = preg_replace('/^  /m', '', $pre);
		return $pre;
	}

	//	Params:
	//	$text - string to process with html <p> tags
	protected function formParagraphs($text) {
	// Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);

		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		// Wrap <p> tags and unhashify HTML blocks
		foreach ($grafs as $key => $value) {
			if (!preg_match('/^B\x1A[0-9]+B$/', $value)) {
				// Is a paragraph.
				$value = $this->runSpanGamut($value);
				$value = preg_replace('/^([ ]*)/', "<p>", $value);
				$value .= "</p>";
				$grafs[$key] = $this->unhash($value);
			} else {
				// Is a block.
				// Modify elements of @grafs in-place...
				$graf = $value;
				$block = $this->html_hashes[$graf];
				$graf = $block;
				$grafs[$key] = $graf;
			}
		}

		return implode("\n\n", $grafs);
	}

	protected function encodeAttribute($text) {
		// Encode text for a double-quoted HTML attribute. This function
		// is *not* suitable for attributes enclosed in single quotes.
		$text = $this->encodeAmpsAndAngles($text);
		$text = str_replace('"', '&quot;', $text);
		return $text;
	}

	protected function encodeAmpsAndAngles($text) {
		// Smart processing for ampersands and angle brackets that need to
		// be encoded. Valid character entities are left alone unless the
		// no-entities mode is set.
		if ($this->no_entities) {
			$text = str_replace('&', '&amp;', $text);
		} else {
			// Ampersand-encoding based entirely on Nat Irons's Amputator
			// MT plugin: <http://bumppo.net/projects/amputator/>
			$text = preg_replace(
				'/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/',
				'&amp;', 
				$text
			);
		}

		// Encode remaining <'s
		$text = str_replace('<', '&lt;', $text);

		return $text;
	}

	protected function doAutoLinks($text) {
		$text = preg_replace_callback(
			'{<((https?|ftp|dict):[^\'">\s]+)>}i',
			array(&$this, 'doAutoLinks_url_callback'), 
			$text
		);

		// Email addresses: <address@domain.foo>
		$text = preg_replace_callback(
			'{
			<
			(?:mailto:)?
			(
				(?:
					[-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
				|
					".*?"
				)
				\@
				(?:
					[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
				|
					\[[\d.a-fA-F:]+\]
				)
			)
			>
			}xi',
			array(&$this, 'doAutoLinks_email_callback'), 
			$text
		);

		return $text;
	}

	protected function doAutoLinks_url_callback($matches) {
		$url = $this->encodeAttribute($matches[1]);
		$link = "<a href=\"$url\">$url</a>";
		return $this->hashPart($link);
	}

	protected function doAutoLinks_email_callback($matches) {
		$address = $matches[1];
		$link = $this->encodeEmailAddress($address);
		return $this->hashPart($link);
	}

	protected function encodeEmailAddress($addr) {
		//	Input: an email address, e.g. "foo@example.com"
		//
		//	Output: the email address as a mailto link, with each character
		//		of the address encoded as either a decimal or hex entity, in
		//		the hopes of foiling most address harvesting spam bots. E.g.:
		//
		//	  <p><a href="&#109;&#x61;&#105;&#x6c;&#116;&#x6f;&#58;&#x66;o&#111;
		//        &#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;&#101;&#46;&#x63;&#111;
		//        &#x6d;">&#x66;o&#111;&#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;
		//        &#101;&#46;&#x63;&#111;&#x6d;</a></p>
		//
		//	Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
		//   With some optimizations by Milian Wolff.
		$addr = "mailto:" . $addr;
		$chars = preg_split('/(?<!^)(?!$)/', $addr);
		$seed = (int) abs(crc32($addr) / strlen($addr)); // Deterministic seed.

		foreach ($chars as $key => $char) {
			$ord = ord($char);
			// Ignore non-ascii chars.
			if ($ord < 128) {
				$r = ($seed * (1 + $key)) % 100; // Pseudo-random function.

				// roughly 10% raw, 45% hex, 45% dec
				// '@' *must* be encoded. I insist.
				if ($r > 90 && $char != '@') /* do nothing*/;
				else if ($r < 45) $chars[$key] = '&#x' . dechex($ord) . ';';
				else $chars[$key] = '&#' . $ord . ';';
			}
		}

		$addr = implode('', $chars);
		$text = implode('', array_slice($chars, 7)); // text without `mailto:`
		$addr = "<a href=\"$addr\">$text</a>";

		return $addr;
	}

	protected function parseSpan($str) {
		//
		// Take the string $str and parse it into tokens, hashing embeded HTML,
		// escaped characters and handling code spans.
		//
		$output = '';

		$span_re = '{
				(
					\\\\' . $this->escape_chars_re . '
				|
					(?<![`\\\\])
					`+
					' . ( $this->no_markup ? '' : '
				|
					<!--    .*?     -->
				|
					<\?.*?\?> | <%.*?%>
				|
					<[/!$]?[-a-zA-Z0-9:_]+
					(?>
						\s
						(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*
					)?
					>
			') . '
			)
		}xs';

		while (1) {
			// Each loop iteration seach for either the next tag, the next
			// openning code span marker, or the next escaped character.
			// Each token is then passed to handleSpanToken.
			$parts = preg_split($span_re, $str, 2, PREG_SPLIT_DELIM_CAPTURE);

			// Create token from text preceding tag.
			if ($parts[0] != "")
				$output .= $parts[0];

			// Check if we reach the end.
			if (isset($parts[1])) {
				$output .= $this->handleSpanToken($parts[1], $parts[2]);
				$str = $parts[2];
			} else
				break;
		}

		return $output;
	}

	// Handle $token provided by parseSpan by determining its nature and
	// returning the corresponding value that should replace it.
	protected function handleSpanToken($token, &$str) {
		switch ($token{0}) {
			case "\\":
				return $this->hashPart("&#" . ord($token{1}) . ";");
			case "`":
				// Search for end marker in remaining text.
				if (preg_match('/^(.*?[^`])' . preg_quote($token) . '(?!`)(.*)$/sm', $str, $matches)) {
					$str = $matches[2];
					$codespan = $this->makeCodeSpan($matches[1]);
					return $this->hashPart($codespan);
				}

				return $token; // return as text since no ending marker found.
			default:
				return $this->hashPart($token);
		}
	}

	// Remove one level of line-leading tabs or spaces
	protected function outdent($text) {
		return preg_replace('/^(\t|[ ]{1,' . $this->tab_width . '})/m', '', $text);
	}

	// String length protected function for detab. `_initDetab` will create a protected function to
	// hanlde UTF-8 if the default protected function does not exist.
	protected function detab($text) {
		// Replace tabs with the appropriate amount of space.
		//
		// For each line we separate the line in blocks delemited by
		// tab characters. Then we reconstruct every line by adding the
		// appropriate number of space between each blocks.
		$text = preg_replace_callback('/^.*\t.*$/m', array(&$this, '_detab_callback'), $text);

		return $text;
	}

	protected function _detab_callback($matches) {
		$line = $matches[0];

		// strlen protected function for UTF-8.
		$strlen = $this->utf8_strlen;

		// Split in blocks.
		$blocks = explode("\t", $line);

		// Add each blocks to the line.
		$line = $blocks[0];
		unset($blocks[0]); // Do not add first block twice.

		foreach ($blocks as $block) {
			// Calculate amount of space, insert spaces, insert block.
			$amount = $this->tab_width - $strlen($line, 'UTF-8') % $this->tab_width;
			$line .= str_repeat(" ", $amount) . $block;
		}

		return $line;
	}

	// Check for the availability of the protected function in the `utf8_strlen` property
	// (initially `mb_strlen`). If the protected function is not available, create a
	// protected function that will loosely count the number of UTF-8 characters with a
	// regular expression.
	protected function initDetab() {
		if (function_exists($this->utf8_strlen))
			return;

		$this->utf8_strlen = create_function(
			'$text', 
			'return preg_match_all("/[\\\\x00-\\\\xBF]|[\\\\xC0-\\\\xFF][\\\\x80-\\\\xBF]*/", $text, $m);'
		);
	}

	// Swap back in all the tags hashed by _HashHTMLBlocks.
	protected function unhash($text) {
		return preg_replace_callback('/(.)\x1A[0-9]+\1/', array(&$this, '_unhash_callback'), $text);
	}

	protected function _unhash_callback($matches) {
		return $this->html_hashes[$matches[0]];
	}
}