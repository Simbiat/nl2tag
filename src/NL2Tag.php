<?php
declare(strict_types = 1);

namespace Simbiat\HTML;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use function in_array;

/**
 * Class to convert new lines to various HTML tags: `br`, `p` and `li`.
 */
class NL2Tag
{
    /**
     * List of new lines for the respective regex. \R is the main thing, but since we are dealing with HTML, we can also have HTML entities, that we also need to deal with
     * @var string
     */
    public const string NEW_LINES_REGEX = '&#10;|&#11;|&#12;|&#13;|&#133;|&#8232;|&#8233;|\R';
    /**
     * List of self-closing tags
     * @var array|string[]
     */
    public const array VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    /**
     * Common tags, in which you may want to preserve the new lines
     * @var array|string[]
     */
    public const array PRESERVE_SPACE_IN = ['pre', 'textarea', 'code', 'samp', 'kbd', 'var'];
    /**
     * Modifiable list of tags, inside which we preserve spaces
     * @var array|string[]
     */
    public array $preserve_spaces_in = [];
    /**
     * Tags that are allowed in `p`, except for `area`, `link` and `meta`, that may be included under certain conditions.
     * Add them manually (`setPhrasingContent`) along with any other custom tags, if you know that they can be in the piece of text you are parsing.
     *
     * @var array|string[]
     */
    public const array PHRASING_CONTENT = ['a', 'abbr', 'audio', 'b', 'bdi', 'bdo', 'br', 'button', 'canvas', 'cite', 'code', 'data', 'datalist',
        'del', 'dfn', 'em', 'embed', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'map', 'mark', 'math', 'meter',
        'noscript', 'object', 'output', 'picture', 'progress', 'q', 'ruby', 's', 'samp', 'script', 'select', 'slot', 'small',
        'span', 'strong', 'sub', 'sup', 'svg', 'template', 'textarea', 'time', 'u', 'var', 'video', 'wbr'];
    /**
     * Modifiable list of tags, that area allowed in `p`
     * @var array|string[]
     */
    public array $phrasing_content = [];
    /**
     * Tags that are allowed in `li`, except for `area`, `link`, `main` and `meta`, that may be included under certain conditions.
     * @var array|string[]
     */
    public const array FLOW_CONTENT = ['a', 'abbr', 'address', 'article', 'aside', 'audio', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'button', 'canvas', 'cite', 'code',
        'data', 'datalist', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'em', 'embed', 'fieldset', 'figure', 'footer', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'main', 'map',
        'mark', 'math', 'menu', 'meter', 'nav', 'noscript', 'object', 'ol', 'output', 'p', 'picture', 'pre', 'progress', 'q', 'ruby', 's',
        'samp', 'script', 'section', 'select', 'slot', 'small', 'span', 'strong', 'sub', 'sup', 'svg', 'table', 'template', 'textarea', 'time',
        'u', 'ul', 'var', 'video', 'wbr'];
    /**
     * Modifiable list of tags that are allowed in `li`
     * @var array|string[]
     */
    public array $flow_content = [];
    /**
     * Tags, which are used only as wrappers and would generally have whitespace for readability only
     * @var array|string[]
     */
    public const array WRAPPER_ONLY = ['audio', 'col', 'colgroup', 'datalist', 'dl', 'fieldset', 'map', 'math', 'menu', 'ol', 'optgroup', 'picture', 'select', 'table', 'tbody', 'tfooter', 'thead', 'tr', 'ul', 'video',];
    /**
     * Modifiable list of tags, which are used only as wrappers and would generally have whitespace for readability only
     * @var array|string[]
     */
    public array $wrapper_only = [];
    /**
     * Tags, that are always expected to be inside wrappers and can have meaningful whitespace in them
     * @var array|string[]
     */
    public const array INSIDE_WRAPPERS_ONLY = ['caption', 'dd', 'dt', 'li', 'option', 'td', 'th'];
    /**
     * Modifiable list of tags, that are always expected to be inside wrappers and can have meaningful whitespace in them
     * @var array|string[]
     */
    public array $inside_wrappers_only = [];
    /**
     * Flag to add <br> when we have non-phrasing content while wrapping n paragraph or inside tags, where we do not preserve newlines
     * @var bool
     */
    public bool $situational_br = true;
    /**
     * Flag to indicate that we want to collapse new lines. This will replace multiple <br> and remove empty paragraphs and list items
     * @var bool
     */
    public bool $collapse_new_lines = true;
    /**
     * Flag to preserve empty paragraphs with non-breaking space. Can be useful, when you use something like `<p>&nbsp;</p> for visual separation of text.
     * @var bool
     */
    public bool $preserve_non_breaking_space = false;
    
    /**
     * Convert new lines to `br` tags
     * @param string $string
     *
     * @return string
     */
    public function nl2br(string $string): string
    {
        return $this->magic($string, 'br');
    }
    
    /**
     * Convert new lines to `p` tags
     * @param string $string
     *
     * @return string
     */
    public function nl2p(string $string): string
    {
        return $this->magic($string);
    }
    
    /**
     * Convert new lines to `li` tags
     *
     * @param string $string    String to process
     * @param string $list_type List type (`ul`, `ol` or `menu`)
     *
     * @return string
     */
    public function nl2li(string $string, #[ExpectedValues(['ul', 'ol', 'menu'])] string $list_type = 'ul'): string
    {
        return $this->magic($string, 'li', $list_type);
    }
    
    /**
     * The same as `nl2li` but if the first character is not one of `*`, `+` or `-` a sublist (that is new `<ul>`) will be started until another line like that or the end of string will be encountered.
     * @param string $string
     *
     * @return string
     */
    public function changelog(string $string): string
    {
        return $this->magic($string, 'li', changelog: true);
    }
    
    /**
     * Function doing the main processing
     *
     * @param string $string    String to process
     * @param string $wrapper   Wrapper to use (`p`, `li` or `br`)
     * @param string $list_type List type (`ul`, `ol` or `menu`), if `li` is used
     * @param bool   $changelog Flag to generate changelog if `li` is used
     *
     * @return string
     */
    private function magic(string $string, #[ExpectedValues(['p', 'li', 'br'])] string $wrapper = 'p', #[ExpectedValues(['ul', 'ol', 'menu'])] string $list_type = 'ul', bool $changelog = false): string
    {
        #Force lower case for a wrapper type
        $wrapper = mb_strtolower($wrapper, 'UTF-8');
        if (!in_array($wrapper, ['p', 'li', 'br'])) {
            throw new \UnexpectedValueException('Unsupported wrapper tag type `'.$wrapper.'` provided.');
        }
        #Force lower case for list type
        $list_type = mb_strtolower($list_type, 'UTF-8');
        if (!in_array($list_type, ['menu', 'ol', 'ul'])) {
            throw new \UnexpectedValueException('Unsupported list type `'.$list_type.'` provided.');
        }
        #Trim new lines
        $string = $this->trimNewLines($string);
        #Clean up whitespace between tags inside wrappers (if any) to prevent extra newlines
        $string = \preg_replace('/(<((\/('.\implode('|', \array_unique(\array_merge(self::INSIDE_WRAPPERS_ONLY, $this->inside_wrappers_only))).'))|('.\implode('|', \array_unique(\array_merge(self::WRAPPER_ONLY, $this->wrapper_only))).'))[^>]*>)(('.self::NEW_LINES_REGEX.'|\s|\p{C})*<)/mui', '$1<', $string);
        #Check if there are any new lines
        if (!$this->hasNewLines($string)) {
            if ($wrapper === 'br') {
                #Return as is
                return $string;
            }
            if ($wrapper === 'p') {
                #Check if string has non-phrasing content
                if ($this->hasNonPhrasing($string)) {
                    #Return as is
                    return $string;
                }
                #Check if already wrapped
                if ($this->isWrapped($string)) {
                    #Return as is
                    return $string;
                }
                #Return wrapped in <p>
                return '<p>'.$string.'</p>';
            }
            if ($wrapper === 'li') {
                #Check if string has non-flow content
                if ($this->hasNonFlow($string)) {
                    #Return as is
                    return $string;
                }
                #Check if it's already a list
                if ($this->isWrapped($string, 'ul') || $this->isWrapped($string, 'ol') || $this->isWrapped($string, 'menu')) {
                    #Return as is
                    return $string;
                }
                #Return wrapped in list type and <li>
                if ($changelog) {
                    return '<'.$list_type.' class="changelog_list"><li class="changelog_change">'.$string.'</li></'.$list_type.'>';
                }
                return '<'.$list_type.'><li>'.$string.'</li></'.$list_type.'>';
            }
        }
        #Check if it's already a paragraph or list
        if (($wrapper === 'p' && $this->isWrapped($string)) || ($wrapper === 'li' && ($this->isWrapped($string, 'ul') || $this->isWrapped($string, 'ol')))) {
            #Return as is
            return $string;
        }
        #Split the string by new lines
        $split_string = \preg_split('/('.self::NEW_LINES_REGEX.')+/ui', $string, -1, \PREG_SPLIT_DELIM_CAPTURE);
        #Prepare some variables
        if ($wrapper === 'li') {
            if ($changelog) {
                $new_string = '<ul class="changelog_list">';
            } else {
                $new_string = '<ul>';
            }
        } else {
            $new_string = '';
        }
        $between_tags_string = '';
        $unclosed_previous = [];
        $has_not_allowed = false;
        $open_changelog_sublist = false;
        #Process line by line
        foreach ($split_string as $part) {
            #Check if string has non-flow (for lists) or non-phrasing (for paragraphs) content. This is not required for <br>
            if (in_array($wrapper, ['p', 'li'])) {
                $has_not_allowed_current = match ($wrapper) {
                    'p' => $this->hasNonPhrasing($part),
                    'li' => $this->hasNonFlow($part),
                };
                #If any of the previous lines had non-flow or non-phrasing content, we need to set the respective flag as such.
                if ($has_not_allowed || $has_not_allowed_current) {
                    $has_not_allowed = true;
                }
            }
            #Check if string has any non-closed tags
            $unclosed_current = $this->hasUnclosedTags($part);
            if ($wrapper === 'li' && $changelog) {
                #Get the changelog type
                $changelog_type = $this->getChangelogType($between_tags_string.$part);
            }
            #Check if we have any unmatched tags on either current or previous line
            if (empty($unclosed_current['opening']) && empty($unclosed_current['closing']) && empty($unclosed_previous)) {
                #Check if the line is a set of newlines or other whitespace
                if (\preg_match('/^('.self::NEW_LINES_REGEX.'|\s|\p{C})*$/ui', $part) === 1) {
                    #If we are here, it means, that we are outside any tags or text nodes, which are probably already wrapped (or do not need wrapping).
                    #Essentially it means that this is just the captured delimiter from the split
                    continue;
                }
                #And if it has only allowed content
                if ($has_not_allowed) {
                    #Add the current string as is
                    $new_string .= $part;
                    #Wrap and add to new string
                } elseif ($wrapper === 'br') {
                    $new_string .= $part.'<br>';
                } elseif ($wrapper === 'p') {
                    #Wrap in <p> and add to new string
                    if ($this->isWrapped($part)) {
                        $new_string .= $part;
                    } else {
                        $new_string .= '<p>'.$this->trimBRs($part).'</p>';
                    }
                } elseif ($wrapper === 'li') {
                    if ($this->isWrapped($part, 'li')) {
                        $new_string .= $part;
                    } elseif ($changelog && !empty($changelog_type)) {
                        if ($changelog_type === 'ul') {
                            #Check if we already have open sub-list
                            if ($open_changelog_sublist) {
                                #Close it and open new one
                                $new_string .= '</ul><li class="changelog_sublist_name">'.$this->trimBRs($part).'</li><ul class="changelog_sublist">';
                            } else {
                                #Open sub-list
                                $new_string .= '<li class="changelog_sublist_name">'.$this->trimBRs($part).'</li><ul class="changelog_sublist">';
                                $open_changelog_sublist = true;
                            }
                        } else {
                            $new_string .= $this->wrapChangelog($part, $changelog_type);
                        }
                    } else {
                        $new_string .= '<li>'.$this->trimBRs($part).'</li>';
                    }
                }
                #Reset the flag for non-flow/non-phrasing content
                $has_not_allowed = false;
                continue;
            }
            #If we have unmatched closing tags on current line, check if we had any unmatched opening tags on previous line(s)
            $this->closeUnclosed($unclosed_previous, $unclosed_current);
            #Add any current unmatched opening tags to list of previously unmatched ones
            $this->updateUnclosed($unclosed_previous, $unclosed_current);
            #Check if we still have some unclosed tags
            if (empty($unclosed_previous)) {
                #Add all previously saved parts and the current part to the new string
                if ($wrapper === 'br') {
                    $new_string .= $this->trimBRs($between_tags_string.$part).'<br>';
                } elseif ($has_not_allowed) {
                    $new_string .= $between_tags_string.$part;
                } elseif ($wrapper === 'p') {
                    if ($this->isWrapped($part)) {
                        $new_string .= $between_tags_string.$part;
                    } else {
                        $new_string .= '<p>'.$this->trimBRs($between_tags_string.$part).'</p>';
                    }
                } elseif ($wrapper === 'li') {
                    if ($this->isWrapped($part)) {
                        $new_string .= $between_tags_string.$part;
                    } elseif ($changelog && !empty($changelog_type)) {
                        if ($changelog_type === 'ul') {
                            #Check if we already have open sub-list
                            if ($open_changelog_sublist) {
                                #Close it and open new one
                                $new_string .= '</ul><li class="changelog_sublist_name">'.$this->trimBRs($between_tags_string.$part).'</li><ul class="changelog_sublist">';
                            } else {
                                #Open sub-list
                                $new_string .= '<li class="changelog_sublist_name">'.$this->trimBRs($between_tags_string.$part).'</li><ul class="changelog_sublist">';
                                $open_changelog_sublist = true;
                            }
                        } else {
                            $new_string .= $this->wrapChangelog($between_tags_string.$part, $changelog_type);
                        }
                    } else {
                        $new_string .= '<li>'.$this->trimBRs($between_tags_string.$part).'</li>';
                    }
                }
                #Reset between tags string
                $between_tags_string = '';
                #Reset flag for non-flow/non-phrasing content
                $has_not_allowed = false;
                #Check if the line is a set of newlines or other whitespace
            } elseif (\preg_match('/^('.self::NEW_LINES_REGEX.'|\s|\p{C})*$/ui', $part) === 1) {
                #Add <br> to the line, if we do not have tags, that need new lines preservation and user agreed to add extra <br> tags
                if ($this->hasToPreserve($unclosed_previous)) {
                    $between_tags_string .= $part;
                } elseif ($this->situational_br && (!$this->hasOpenWrappers($unclosed_previous) || $this->hasOpenInsideWrappers($unclosed_previous))) {
                    $between_tags_string .= '<br>';
                }
            } else {
                #Save the part for the time, we can reattach it, once all tags are closed
                $between_tags_string .= $part;
            }
        }
        #It is possible, that even when we iterate over all lines, some unclosed tags remain. In such a case, we keep the rest of the text as is
        if (!empty($unclosed_previous)) {
            $new_string .= $between_tags_string;
        }
        #If we had any sub-lists, there will be an unclosed ul, which we need to close
        if ($wrapper === 'li' && $changelog && $open_changelog_sublist) {
            $new_string .= '</ul>';
        }
        #Trim potentially excessive <br> tags
        $new_string = $this->trimBRs($new_string);
        if ($this->collapse_new_lines) {
            $new_string = $this->collapseNewLines($new_string);
        }
        if ($wrapper === 'li') {
            $new_string .= '</ul>';
        }
        #Clean up potential extra <br> tags between <p> or <li> elements
        $new_string = \preg_replace('/(<\/(?>p|li)>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*((?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<(?>p|li)(?>\s+|>))/ui', '$1$2', $new_string);
        #Do the same for </li> followed by </ul>, </ol> or </menu>
        $new_string = \preg_replace('/(<\/li>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*((?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/(?>ul|ol|menu)\s*>)/ui', '$1$2', $new_string);
        #Same for <ul>, <ol> or <menu> followed by <li>
        $new_string = \preg_replace('/(<(?>(?>ul|ol|menu)[^>]*)>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*((?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<li(?>\s+|>))/ui', '$1$2', $new_string);
        #Same between <details> and <summary>
        $new_string = \preg_replace('/(<(?>details[^>]*)>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*((?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<summary(?>\s+|>))/ui', '$1$2', $new_string);
        #Same inside <summary> (essentially we are trimming it)
        $new_string = \preg_replace('/(<(?>summary[^>]*)>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*/ui', '$1', $new_string);
        #Also "trim" actual contents of <details>, that is text after </summary> and before </details>
        $new_string = \preg_replace('/(<\/summary>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*/ui', '$1', $new_string);
        #Similarly trim <blockquote> content
        $new_string = \preg_replace('/(<(?>blockquote[^>]*)>)(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*/ui', '$1', $new_string);
        #With this we trim before the closing tags of the above-mentioned elements
        $new_string = \preg_replace('/(?>(?>'.self::NEW_LINES_REGEX.'|\s|\p{C})*<\/?br\s*\/?\s*>)*(<\/(?>blockquote|details|summary))/ui', '$1', $new_string);
        #Elements that are supposed to have preserve spaces, should not have <br> elements in them, so we remove them as well
        return $this->removeBRs($new_string);
    }
    
    /**
     * Elements that are supposed to have preserve spaces, should not have <br> elements in them, so we remove them as well
     * @param string $string
     *
     * @return string
     */
    private function removeBRs(string $string): string
    {
        $wrapped_in_html = false;
        if (\preg_match('/^\s*<html( [^<>]*)?>.*<\/html>\s*$/uis', $string) === 1) {
            $wrapped_in_html = true;
        } else {
            #Suppressing inspection, since we do not need the language for the purpose of the library
            /** @noinspection HtmlRequiredLangAttribute */
            $string = '<html>'.$string.'</html>';
        }
        $html = new \DOMDocument(encoding: 'UTF-8');
        #mb_convert_encoding is done as per workaround for UTF-8 loss/corruption on load from https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
        #LIBXML_HTML_NOIMPLIED and LIBXML_HTML_NOTED to avoid adding wrappers (html, body, DTD). This will also allow fewer issues in case string has both regular HTML and some regular text (outside any tags). LIBXML_NOBLANKS to remove empty tags if any. LIBXML_PARSEHUGE to allow processing of larger strings. LIBXML_COMPACT for some potential optimization. LIBXML_NOWARNING and LIBXML_NOERROR to suppress warning in case of malformed HTML. LIBXML_NONET to protect from unsolicited connections to external sources.
        $html->loadHTML(mb_convert_encoding($string, 'HTML-ENTITIES', 'UTF-8'), \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | \LIBXML_NOBLANKS | \LIBXML_PARSEHUGE | \LIBXML_COMPACT | \LIBXML_NOWARNING | \LIBXML_NOERROR | \LIBXML_NONET);
        $html->preserveWhiteSpace = false;
        $html->formatOutput = false;
        $html->normalizeDocument();
        #Get elements
        $xpath = new \DOMXPath($html);
        $elements = $xpath->query('//'.\implode(' | //', \array_unique(\array_merge(self::PRESERVE_SPACE_IN, $this->preserve_spaces_in))));
        #Replace <br> tags with new line
        foreach ($elements as $element) {
            $br_elements = $element->getElementsByTagName('br');
            while ($br_elements->length > 0) {
                $newline_text_node = $html->createTextNode("\n");
                $br_element = $br_elements->item(0);
                $br_element->parentNode->replaceChild($newline_text_node, $br_element);
            }
        }
        #Get the cleaned HTML
        $cleaned_html = $html->saveHTML();
        #Strip the excessive HTML tags if we added them
        if (!$wrapped_in_html) {
            $cleaned_html = \preg_replace('/(^\s*<html( [^<>]*)?>)(.*)(<\/html>\s*$)/uis', '$3', $cleaned_html);
        }
        return \preg_replace('/(^\s*<html( [^<>]*)?>)(.*)(<\/html>\s*$)/uis', '$3', $cleaned_html);
    }
    
    /**
     * Function to determine the changelog entry type for string
     * @param string $string
     *
     * @return string
     */
    private function getChangelogType(string $string): string
    {
        #Strip all tags
        $string = \strip_tags($string);
        #Get first non-whitespace character
        $character = \preg_replace('/^([\s\p{C}]*)(\S)(.*)$/ui', '$2', $string);
        return match ($character) {
            '*', '-', '+' => $character,
            default => 'ul',
        };
    }
    
    /**
     * Function to wrap changelog line in the appropriate list item
     *
     * @param string $string         Changelog line
     * @param string $changelog_type Changelog type
     *
     * @return string
     */
    private function wrapChangelog(string $string, string $changelog_type): string
    {
        if (!in_array($changelog_type, ['*', '+', '-'])) {
            throw new \UnexpectedValueException('Unsupported changelog type `'.$changelog_type.'` provided.');
        }
        return match ($changelog_type) {
            '*' => '<li class="changelog_change">'.$this->trimBRs($this->removeChangelogType($string)).'</li>',
            '+' => '<li class="changelog_addition">'.$this->trimBRs($this->removeChangelogType($string)).'</li>',
            '-' => '<li class="changelog_removal">'.$this->trimBRs($this->removeChangelogType($string)).'</li>',
        };
    }
    
    /**
     * Function to remove character for the changelog type from string
     * @param string $string
     *
     * @return string
     */
    private function removeChangelogType(string $string): string
    {
        return \preg_replace('/^((<[^<>]+>)*)(\s*)([*+-])(\s*)(.*)$/u', '$1$6', $string);
    }
    
    /**
     * Function to "close" previously opened tags, while we are iterating
     *
     * @param array $unclosed_previous Previously unclosed tags
     * @param array $unclosed_current  Currently unclosed tags
     *
     * @return void
     */
    private function closeUnclosed(array &$unclosed_previous, array &$unclosed_current): void
    {
        if (!empty($unclosed_current['closing']) && !empty($unclosed_previous)) {
            foreach ($unclosed_current['closing'] as $tag => $count) {
                if (isset($unclosed_previous[$tag])) {
                    $unclosed_previous[$tag] -= $unclosed_current['closing'][$tag];
                    #Remove from the list of current unclosed items
                    unset($unclosed_current['closing'][$tag]);
                    #If we no longer have unclosed opening tags from previous lines - remove them from the list, too
                    if ($unclosed_previous[$tag] <= 0) {
                        unset($unclosed_previous[$tag]);
                    }
                }
            }
        }
    }
    
    /**
     * Function to update the list of unclosed tags
     *
     * @param array $unclosed_previous Previously unclosed tags
     * @param array $unclosed_current  Currently unclosed tags
     *
     * @return void
     */
    private function updateUnclosed(array &$unclosed_previous, array $unclosed_current): void
    {
        if (!empty($unclosed_current['opening'])) {
            foreach ($unclosed_current['opening'] as $tag => $count) {
                if (isset($unclosed_previous[$tag])) {
                    $unclosed_previous[$tag] += $count;
                } else {
                    $unclosed_previous[$tag] = $count;
                }
            }
        }
    }
    
    /**
     * Function to trim new lines from beginning and end of string
     * @param string $string
     *
     * @return string
     */
    private function trimNewLines(string $string): string
    {
        return \preg_replace('/('.self::NEW_LINES_REGEX.')+$/ui', '', \preg_replace('/^('.self::NEW_LINES_REGEX.')+/ui', '', $string));
    }
    
    /**
     * Function to trim `<br>` from beginning and end of string
     * @param string $string
     *
     * @return string
     */
    private function trimBRs(string $string): string
    {
        return \preg_replace('/(<\/?br\s*\/?\s*>)+$/ui', '', \preg_replace('/^(<\/?br\s*\/?\s*>)+/ui', '', $string));
    }
    
    /**
     * Function to collapse new lines
     * @param string $string
     *
     * @return string
     */
    private function collapseNewLines(string $string): string
    {
        #Collapse <br>s
        $string = \preg_replace('/(\s*<\/?br\s*\/?\s*>\s*)+/ui', '<br>', $string);
        #Remove <br>s between paragraphs
        $string = \preg_replace('/(<p\s*([^<>]+)?>)((\s*<\/?br\s*\/?\s*>\s*)+)(<\/p\s*>)/ui', '$1$3', $string);
        #Remove empty paragraphs
        if ($this->preserve_non_breaking_space) {
            #Since \p{Z} and \h, which are part of \s, include non-breaking space, we have to expand them
            return \preg_replace('/\s*<p\s*([^<>]+)?>[\v\p{C}\x{0020}\x{1680}\x{180E}\x{2000}\x{2001}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200A}\x{200B}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]*<\/p\s*>\s*/ui', '', $string);
        }
        return \preg_replace('/\s*<p\s*([^<>]+)?>[\s\p{C}]*<\/p\s*>\s*/ui', '', $string);
    }
    
    /**
     * Function to check if string has any new lines
     * @param string $string
     *
     * @return bool
     */
    private function hasNewLines(string $string): bool
    {
        $result = \preg_match('/'.self::NEW_LINES_REGEX.'/ui', $string);
        return $result === 1;
    }
    
    /**
     * Function to check if string is already wrapped in a tag
     * @param string $string String to check
     * @param string $tag    Tag to check against
     *
     * @return bool
     */
    private function isWrapped(string $string, string $tag = 'p'): bool
    {
        return \preg_match('/^<'.$tag.'(\s*|\s+[^<>]+)>.*<\/'.$tag.'\s*>$/ui', $string) === 1;
    }
    
    /**
     * Function to check if list of unclosed tags has those that require preservation of new lines
     *
     * @param array $open_tags
     *
     * @return bool
     */
    private function hasToPreserve(array $open_tags): bool
    {
        return $this->hasOpenTags($open_tags, \array_unique(\array_merge(self::PRESERVE_SPACE_IN, $this->preserve_spaces_in)));
    }
    
    /**
     * Function to check if we have open wrapper tags
     *
     * @param array $open_tags
     *
     * @return bool
     */
    private function hasOpenWrappers(array $open_tags): bool
    {
        return $this->hasOpenTags($open_tags, \array_unique(\array_merge(self::WRAPPER_ONLY, $this->wrapper_only)));
    }
    
    /**
     * Function to check if we have open wrapper tags
     *
     * @param array $open_tags
     *
     * @return bool
     */
    private function hasOpenInsideWrappers(array $open_tags): bool
    {
        return $this->hasOpenTags($open_tags, \array_unique(\array_merge(self::INSIDE_WRAPPERS_ONLY, $this->inside_wrappers_only)));
    }
    
    /**
     * Generalized function to check if currently open tags has any tag from a list
     *
     * @param array $open_tags Currently open tags
     * @param array $list      List of tags to check against
     *
     * @return bool
     */
    private function hasOpenTags(array $open_tags, array $list): bool
    {
        $open_tags = \array_keys($open_tags);
        return \array_any($open_tags, static fn($tag) => in_array(mb_strtolower($tag, 'UTF-8'), $list, true));
    }
    
    /**
     * Function to check if string has non-phrasing content tags
     * @param string $string
     *
     * @return bool
     */
    private function hasNonPhrasing(string $string): bool
    {
        #Check for tags, which are not phrasing content. Checking only for opening tags, since orphaned closing tags can trigger this,
        #but we do not care for them, since normally they won't break the HTML
        return \preg_match('/<(?!p|\/|'.\implode('|', \array_unique(\array_merge(self::PHRASING_CONTENT, $this->phrasing_content))).')[^>]*>/ui', $string) === 1;
    }
    
    /**
     * Function to check if string has non-phrasing content tags
     * @param string $string
     *
     * @return bool
     */
    private function hasNonFlow(string $string): bool
    {
        #Check for tags, which are not flow content. Checking only for opening tags, since orphaned closing tags can trigger this,
        #but we do not care for them, since normally they won't break the HTML
        return \preg_match('/<(?!p|\/|'.\implode('|', \array_unique(\array_merge(self::FLOW_CONTENT, $this->flow_content))).')[^>]*>/ui', $string) === 1;
    }
    
    /**
     * Function to check if string has unclosed tags
     * @param string $string
     *
     * @return array
     */
    private function hasUnclosedTags(string $string): array
    {
        #Get all opening tags. This will also match self-closing tags, if they have "/" at the end or do not use it at all
        \preg_match_all('/<([a-zA-Z\-]+)(\s*\/?| [^<>]+)?>/ui', $string, $found_tags, \PREG_PATTERN_ORDER);
        $opening_tags = $found_tags[0];
        #Get all closing tags. This will also match self-closing tags, if they have "/" at the beginning
        \preg_match_all('/<\/([a-zA-Z\-]+)\s*>/ui', $string, $found_tags, \PREG_PATTERN_ORDER);
        $closing_tags = $found_tags[0];
        #Remove all self-closing tags from opening tags
        foreach ($opening_tags as $key => $tag) {
            #Get the real tag name
            $tag = mb_strtolower(\preg_replace('/<([a-zA-Z\-]+)(\s*\/?| [^<>]+)?>/ui', '$1', $tag), 'UTF-8');
            #Check if self-closing
            if (in_array($tag, self::VOID_ELEMENTS, true)) {
                #Remove from the array
                unset($opening_tags[$key]);
            } else {
                $opening_tags[$key] = $tag;
            }
        }
        #Remove all self-closing tags from closing tags
        foreach ($closing_tags as $key => $tag) {
            #Get the real tag name
            $tag = mb_strtolower(\preg_replace('/<\/([a-zA-Z\-]+)\s*>/ui', '$1', $tag), 'UTF-8');
            #Check if self-closing
            if (in_array($tag, self::VOID_ELEMENTS, true)) {
                #Remove from the array
                unset($closing_tags[$key]);
            } else {
                $closing_tags[$key] = $tag;
            }
        }
        #Count unique tags
        $unique_tags = [
            'opening' => \array_count_values($opening_tags),
            'closing' => \array_count_values($closing_tags),
        ];
        #Compare arrays and leave only tags, that are unmatched
        foreach ($unique_tags['opening'] as $tag => $count) {
            if (isset($unique_tags['closing'][$tag]) && $unique_tags['closing'][$tag] === $count) {
                unset($unique_tags['opening'][$tag], $unique_tags['closing'][$tag]);
            }
        }
        #Do the same for the closing tags, too. Not sure if we can get any "hits" on this cycle, but my gut feeling says, that we better check
        foreach ($unique_tags['closing'] as $tag => $count) {
            if (isset($unique_tags['opening'][$tag]) && $unique_tags['opening'][$tag] === $count) {
                unset($unique_tags['closing'][$tag], $unique_tags['opening'][$tag]);
            }
        }
        return $unique_tags;
    }
}
