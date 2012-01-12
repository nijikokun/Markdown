#Markdown for PHP#

Cleaned up various versions around the web, joined them together for a more in-depth version, fixed some issues as well as formatting.

## License

Licensed under AOL <http://aol.nexua.org>

## Usage

### PHP >= 5

    $text = 'Some _text_ ';
    $m = new Markdown();
    echo $m->transform($text);

### PHP >= 5.3

    $text = 'Some _text_ ';
    $m = new Markdown();
    echo $m($text);


## In-Depth Examples
    Phrase Emphasis

    *italic*   **bold**
    _italic_   __bold__

    Links

    Inline:

    An [example](http://url.com/ "Title")

    Reference-style labels (titles are optional):

    An [example][id]. Then, anywhere
    else in the doc, define the link:

      [id]: http://example.com/  "Title"

    Images

    Inline (titles are optional):

    ![alt text](/path/img.jpg "Title")

    Reference-style:

    ![alt text][id]

      [id]: /url/to/img.jpg "Title"

    Headers

    Setext-style:

    Header 1
    ========

    Header 2
    --------

    atx-style (closing #'s are optional):

    # Header 1 #

    ## Header 2 ##

    ###### Header 6

    Lists

    Ordered, without paragraphs:

    1.  Foo
    2.  Bar

    Unordered, with paragraphs:

    *   A list item.

        With multiple paragraphs.

    *   Bar

    You can nest them:

    *   Abacus
        * Math
    *   Just
        1.  A
        2.  List
            * See
        3. Oh
    *   Cool!

    Blockquotes

    > Email-style angle brackets
    > are used for blockquotes.

    > > And, they can be nested.

    > #### Headers in blockquotes
    >
    > * You can quote a list.
    > * Etc.

    Code Spans

    `<code>` spans are delimited
    by backticks.

    You can include literal backticks
    like `` `this` ``.

    Preformatted Code Blocks

    Indent every line of a code block by at least 4 spaces or 1 tab.

    This is a normal paragraph.

        This is a preformatted
        code block.

    Horizontal Rules

    Three or more dashes or asterisks:

    ---

    * * *

    - - - -

    Manual Line Breaks

    End a line with two or more spaces:

    Roses are red,  
    Violets are blue.
    
## Credits

*2012* Nijikokun <nijikokun@gmail.com> @nijikokun

*2010* Sean Sandy <http://seanja.com/>

*2009* Michel Fortin <http://michelf.com/>