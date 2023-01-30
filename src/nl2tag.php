<?php
declare(strict_types=1);
namespace Simbiat;

class nl2tag
{
    #List of new lines for respective regex. \R is the main thing, but since we are dealing with HTML, we can also have HTML entities, that we also need to deal with
    public const newLinesRegex = '(&#10;|&#11;|&#12;|&#13;|&#133;|&#8232;|&#8233;|\R)';
    #List of self-closing tags
    public const voidElements = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    #Common tags, in which you may want to preserve the new lines
    public const preserveSpacesIn = ['pre', 'textarea', 'code', 'samp', 'kbd', 'var'];
    private array $preserveSpacesIn;
    #Tags that are allowed in `p`, except for `area`, `link` and `meta`, that may be included under certain conditions.
    #Add them manually along with any other custom tags, if you know that they can be in the piece of text you are parsing.
    public const phrasingContent = ['a', 'abbr', 'audio', 'b', 'bdi', 'bdo', 'br', 'button', 'canvas', 'cite', 'code', 'data', 'datalist',
                                    'del', 'dfn', 'em', 'embed', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'map', 'mark', 'math', 'meter',
                                    'noscript', 'object', 'output', 'picture', 'progress', 'q', 'ruby', 's', 'samp', 'script', 'select', 'slot', 'small',
                                    'span', 'strong', 'sub', 'sup', 'svg', 'template', 'textarea', 'time', 'u', 'var', 'video', 'wbr'];
    private array $phrasingContent;
    #Tags that are allowed in `li`, except for `area`, `link`, `main` and `meta`, that may be included under certain conditions.
    public const flowContent = ['a', 'abbr', 'address', 'article', 'aside', 'audio', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'button', 'canvas', 'cite', 'code',
                                'data', 'datalist', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'em', 'embed', 'fieldset', 'figure', 'footer', 'form',
                                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'main', 'map',
                                'mark', 'math', 'menu', 'meter', 'nav', 'noscript', 'object', 'ol', 'output', 'p', 'picture', 'pre', 'progress', 'q', 'ruby', 's',
                                'samp', 'script', 'section', 'select', 'slot', 'small', 'span', 'strong', 'sub', 'sup', 'svg', 'table', 'template', 'textarea', 'time',
                                'u', 'ul', 'var', 'video', 'wbr'];
    private array $flowContent;
    #Tags which are used only as wrappers and would generally have whitespace for readability only
    public const wrapperOnly = ['audio', 'col', 'colgroup', 'datalist', 'dl', 'fieldset', 'map', 'math', 'menu', 'ol', 'optgroup', 'picture', 'select', 'table', 'tbody', 'tfooter', 'thead', 'tr', 'ul', 'video',];
    private array $wrapperOnly;
    #Tags that are always expected to be inside wrappers and can have meaningful whitespace in them
    public const insideWrappersOnly = ['caption', 'dd', 'dt', 'li', 'option', 'td', 'th'];
    private array $insideWrappersOnly;
    #Flag to add <br> when we have non-phrasing content while wrapping n paragraph or inside tags, where we do not preserve newlines
    private bool $situationalBR = true;
    #Flag to indicate, that we want to collapse new lines. This will replace multiple <br> and remove empty paragraphs and list items
    private bool $collapseNewLines = true;
    
    public function __construct()
    {
        #Populate default values of the array based on public constants
        $this->preserveSpacesIn = self::preserveSpacesIn;
        $this->phrasingContent = self::phrasingContent;
        $this->flowContent = self::flowContent;
        $this->wrapperOnly = self::wrapperOnly;
        $this->insideWrappersOnly = self::insideWrappersOnly;
    }
    
    public function nl2br(string $string): string
    {
        return $this->magic($string, 'br');
    }
    
    public function nl2p(string $string): string
    {
        return $this->magic($string);
    }
    
    public function nl2li(string $string, string $listType = 'ul'): string
    {
        return $this->magic($string, 'li', $listType);
    }
    
    #Just a convenient wrapper to generate a changelog list
    public function changelog(string $string): string
    {
        return $this->magic($string, 'li', changelog: true);
    }
    
    #Function doing the main processing
    private function magic(string $string, string $wrapper = 'p', string $listType = 'ul', bool $changelog = false): string
    {
        #Force lower case for wrapper type
        $wrapper = strtolower($wrapper);
        if (!in_array($wrapper, ['p', 'li', 'br'])) {
            throw new \UnexpectedValueException('Unsupported wrapper tag type `'.$wrapper.'` provided.');
        }
        #Force lower case for list type
        $listType = strtolower($listType);
        if (!in_array($listType, ['menu', 'ol', 'ul'])) {
            throw new \UnexpectedValueException('Unsupported list type `'.$listType.'` provided.');
        }
        #Trim new lines
        $string = $this->trimNewLines($string);
        #Check if there are any new lines
        if (!$this->hasNewLines($string)) {
            if ($wrapper === 'br') {
                #Return as is
                return $string;
            } elseif ($wrapper === 'p') {
                #Check if string has non-phrasing content
                if ($this->hasNonPhrasing($string)) {
                    #Return as is
                    return $string;
                } else {
                    #Check if already wrapped
                    if ($this->isWrapped($string)) {
                        #Return as is
                        return $string;
                    } else {
                        #Return wrapped in <p>
                        return '<p>'.$string.'</p>';
                    }
                }
            } elseif ($wrapper === 'li') {
                #Check if string has non-flow content
                if ($this->hasNonFlow($string)) {
                    #Return as is
                    return $string;
                } else {
                    #Check if it's already a list
                    if ($this->isWrapped($string, 'ul') || $this->isWrapped($string, 'ol') || $this->isWrapped($string, 'menu')) {
                        #Return as is
                        return $string;
                    } else {
                        #Return wrapped in list type and <li>
                        if ($changelog) {
                            return '<'.$listType.' class="changelog_list"><li class="changelog_change">'.$string.'</li></'.$listType.'>';
                        } else {
                            return '<'.$listType.'><li>'.$string.'</li></'.$listType.'>';
                        }
                    }
                }
            }
        }
        #Check if it's already a paragraph or list
        if (($wrapper === 'p' && $this->isWrapped($string)) || ($wrapper === 'li' && ($this->isWrapped($string, 'ul') || $this->isWrapped($string, 'ol')))) {
            #Return as is
            return $string;
        }
        #Split the string by new lines
        $splitString = preg_split('/('.self::newLinesRegex.')/ui', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
        #Prepare some variables
        if ($wrapper === 'li') {
            if ($changelog) {
                $newString = '<ul class="changelog_list">';
            } else {
                $newString = '<ul>';
            }
        } else {
            $newString = '';
        }
        $betweenTagsString = '';
        $unclosedPrevious = [];
        $hasNotAllowed = false;
        $openChangelogSubList = false;
        #Process line by line
        foreach ($splitString as $part) {
            #Check if string has non-flow (for lists) or non-phrasing (for paragraphs) content. This is not required for <br>
            if (in_array($wrapper, ['p', 'li'])) {
                $hasNotAllowedCurrent = match($wrapper) {
                    'p' => $this->hasNonPhrasing($part),
                    'li' => $this->hasNonFlow($part),
                };
                #If any of the previous lines had non-flow or non-phrasing content, we need to set the respective flag as such.
                if ($hasNotAllowed || $hasNotAllowedCurrent) {
                    $hasNotAllowed = true;
                }
            }
            #Check if string has any non-closed tags
            $unclosedCurrent = $this->hasUnclosedTags($part);
            if ($wrapper === 'li' && $changelog) {
                #Get changelog type
                $changelogType = $this->getChangelogType($betweenTagsString.$part);
            }
            #Check if we have any unmatched tags on either current or previous line
            if (empty($unclosedCurrent['opening']) && empty($unclosedCurrent['closing']) && empty($unclosedPrevious)) {
                #Check if the line is a set of newlines or other whitespace
                if (preg_match('/^('.self::newLinesRegex.'|\s)*$/ui', $part) === 1) {
                    #If we are here, it means, that we are outside any tags or text nodes, which are probably already wrapped (or do not need wrapping).
                    #Essentially it means that this is just the captured delimiter from the split
                    continue;
                }
                #And if it has only allowed content
                if ($hasNotAllowed) {
                    #Add the current string as is
                    $newString .= $part;
                } else {
                    #Wrap and add to new string
                    if ($wrapper === 'br') {
                        $newString .= $part.'<br>';
                    } elseif ($wrapper === 'p') {
                        #Wrap in <p> and add to new string
                        if ($this->isWrapped($part)) {
                            $newString .= $part;
                        } else {
                            $newString .= '<p>'.$this->trimBRs($part).'</p>';
                        }
                    } elseif ($wrapper === 'li') {
                        if ($this->isWrapped($part, 'li')) {
                            $newString .= $part;
                        } else {
                            if ($changelog && !empty($changelogType)) {
                                if ($changelogType === 'ul') {
                                    #Check if we already have open sub-list
                                    if ($openChangelogSubList) {
                                        #Close it and open new one
                                        $newString .= '</ul><li class="changelog_sublist_name">'.$this->trimBRs($part).'</li><ul class="changelog_sublist">';
                                    } else {
                                        #Open sub-list
                                        $newString .= '<li class="changelog_sublist_name">'.$this->trimBRs($part).'</li><ul class="changelog_sublist">';
                                        $openChangelogSubList = true;
                                    }
                                } else {
                                    #if (preg_match('/.*Added support for HTTP\/2.*/ui', $part) === 1) {
                                    #    var_dump($changelogType);
                                    #    echo htmlentities($this->wrapChangelog($part, $changelogType));exit;
                                    #}
                                    $newString .= $this->wrapChangelog($part, $changelogType);
                                }
                            } else {
                                $newString .= '<li>'.$this->trimBRs($part).'</li>';
                            }
                        }
                    }
                }
                #Reset flag for non-flow/non-phrasing content
                $hasNotAllowed = false;
                continue;
            }
            #If we have unmatched closing tags on current line, check if we had any unmatched opening tags on previous line(s)
            $this->closeUnclosed($unclosedPrevious, $unclosedCurrent);
            #Add any current unmatched opening tags to list of previously unmatched ones
            $this->updateUnclosed($unclosedPrevious, $unclosedCurrent);
            #Check if we still have some unclosed tags from
            if (empty($unclosedPrevious)) {
                #Add all previously saved parts and the current part to the new string
                if ($wrapper === 'br') {
                    $newString .= $this->trimBRs($betweenTagsString.$part).'<br>';
                } else {
                    if ($hasNotAllowed) {
                        $newString .= $betweenTagsString.$part;
                    } else {
                        if ($wrapper === 'p') {
                            if ($this->isWrapped($part)) {
                                $newString .= $betweenTagsString.$part;
                            } else {
                                $newString .= '<p>'.$this->trimBRs($betweenTagsString.$part).'</p>';
                            }
                        } elseif ($wrapper === 'li') {
                            if ($this->isWrapped($part)) {
                                $newString .= $betweenTagsString.$part;
                            } else {
                                if ($changelog && !empty($changelogType)) {
                                    if ($changelogType === 'ul') {
                                        #Check if we already have open sub-list
                                        if ($openChangelogSubList) {
                                            #Close it and open new one
                                            $newString .= '</ul><li class="changelog_sublist_name">'.$this->trimBRs($betweenTagsString.$part).'</li><ul class="changelog_sublist">';
                                        } else {
                                            #Open sub-list
                                            $newString .= '<li class="changelog_sublist_name">'.$this->trimBRs($betweenTagsString.$part).'</li><ul class="changelog_sublist">';
                                            $openChangelogSubList = true;
                                        }
                                    } else {
                                        $newString .= $this->wrapChangelog($betweenTagsString.$part, $changelogType);
                                    }
                                } else {
                                    $newString .= '<li>'.$this->trimBRs($betweenTagsString.$part).'</li>';
                                }
                            }
                        }
                    }
                }
                #Reset between tags string
                $betweenTagsString = '';
                #Reset flag for non-flow/non-phrasing content
                $hasNotAllowed = false;
            } else {
                #Check if the line is a set of newlines or other whitespace
                if (preg_match('/^('.self::newLinesRegex.'|\s)*$/ui', $part) === 1) {
                    #Add <br> to the line, if we do not have tags, that need new lines preservation and user agreed to add extra <br> tags
                    if ($this->hasToPreserve($unclosedPrevious)) {
                        $betweenTagsString .= $part;
                    } else {
                        if ($this->situationalBR && (!$this->hasOpenWrappers($unclosedPrevious) || $this->hasOpenInsideWrappers($unclosedPrevious))) {
                            $betweenTagsString .= '<br>';
                        }
                    }
                } else {
                    #Save the part for the time, we can reattach it, once all tags are closed
                    $betweenTagsString .= $part;
                }
            }
        }
        #It is possible, that even when we iterate over all lines, some unclosed tags remain. In such a case, we keep the rest of the text as is
        if (!empty($unclosedPrevious)) {
            $newString .= $betweenTagsString;
        }
        #If we had any sub-lists, there will be an unclosed ul, which we need to close
        if ($wrapper === 'li' && $changelog && $openChangelogSubList) {
            $newString .= '</ul>';
        }
        #Trim potentially excessive <br> tags
        $newString = $this->trimBRs($newString);
        if ($this->collapseNewLines) {
            $newString = $this->collapseNewLines($newString);
        }
        if ($wrapper === 'li') {
            return $newString.'</ul>';
        } else {
            return $newString;
        }
    }
    
    #Function to determine the changelog entry type for string
    private function getChangelogType(string $string): string
    {
        #Strip all tags
        $string = strip_tags($string);
        #Get first non-whitespace character
        $character = preg_replace('/^(\s*)(\S)(.*)$/ui', '$2', $string);
        return match($character) {
            '*', '-', '+' => $character,
            default => 'ul',
        };
    }
    
    #Function to wrap changelog line in appropriate list item
    private function wrapChangelog(string $string, string $changelogType): string
    {
        if (!in_array($changelogType, ['*', '+', '-'])) {
            throw new \UnexpectedValueException('Unsupported changelog type `'.$changelogType.'` provided.');
        }
        return match ($changelogType) {
            '*' => '<li class="changelog_change">'.$this->trimBRs($this->removeChangelogType($string)).'</li>',
            '+' => '<li class="changelog_addition">'.$this->trimBRs($this->removeChangelogType($string)).'</li>',
            '-' => '<li class="changelog_removal">'.$this->trimBRs($this->removeChangelogType($string)).'</li>',
        };
    }
    
    #Function to remove character for the changelog type from string
    private function removeChangelogType(string $string): string
    {
        return preg_replace('/^((<[^<>]+>)*)(\s*)([*+-])(\s*)(.*)$/ui', '$1$6', $string);
    }
    
    #Function to "close" previously opened tags, while we are iterating
    private function closeUnclosed(array &$unclosedPrevious, array &$unclosedCurrent): void
    {
        if (!empty($unclosedCurrent['closing']) && !empty($unclosedPrevious)) {
            foreach ($unclosedCurrent['closing'] as $tag => $count) {
                if (isset($unclosedPrevious[ $tag ])) {
                    $unclosedPrevious[ $tag ] = $unclosedPrevious[ $tag ] - $unclosedCurrent['closing'][ $tag ];
                    #Remove from list of current unclosed items
                    unset($unclosedCurrent['closing'][ $tag ]);
                    #If we no longer have unclosed opening tags from previous lines - remove them from the list, too
                    if ($unclosedPrevious[ $tag ] <= 0) {
                        unset($unclosedPrevious[ $tag ]);
                    }
                }
            }
        }
    }
    
    #Function to update list of unclosed tags
    private function updateUnclosed(array &$unclosedPrevious, array $unclosedCurrent): void
    {
        if (!empty($unclosedCurrent['opening'])) {
            foreach ($unclosedCurrent['opening'] as $tag=>$count) {
                if (isset($unclosedPrevious[$tag])) {
                    $unclosedPrevious[$tag] = $unclosedPrevious[$tag] + $count;
                } else {
                    $unclosedPrevious[$tag] = $count;
                }
            }
        }
    }
    
    #Function to trim new lines from beginning and end of string
    private function trimNewLines(string $string): string
    {
        return preg_replace('/'.self::newLinesRegex.'+$/ui', '', preg_replace('/^'.self::newLinesRegex.'+/ui', '', $string));
    }
    
    #Function to trim <br> from beginning and end of string
    private function trimBRs(string $string): string
    {
        return preg_replace('/(<\/?br\s*\/?\s*>)+$/ui', '', preg_replace('/^(<\/?br\s*\/?\s*>)+/ui', '', $string));
    }
    
    #Function to collapse new lines
    private function collapseNewLines(string $string): string
    {
        #Collapse <br>s
        $string = preg_replace('/(\s*<\/?br\s*\/?\s*>\s*)+/ui', '<br>', $string);
        #Remove <br>s between paragraphs
        $string = preg_replace('/(<p\s*([^<>]+)?>)((\s*<\/?br\s*\/?\s*>\s*)+)(<\/p\s*>)/ui', '$1$3', $string);
        #Remove empty paragraphs
        return preg_replace('/\s*<p\s*([^<>]+)?>\s*<\/p\s*>\s*/ui', '', $string);
    }
    
    #Function to check if string has any new lines
    private function hasNewLines(string $string): bool
    {
        $result = preg_match('/'.self::newLinesRegex.'/ui', $string);
        if ($result === 1) {
            return true;
        } else {
            return false;
        }
    }
    
    #Function to check if string is already wrapped in a tag
    private function isWrapped(string $string, string $tag = 'p'): bool
    {
        if (preg_match('/^<'.$tag.'(\s*|\s+[^<>]+)>.*<\/'.$tag.'\s*>$/ui', $string) === 1) {
            return true;
        } else {
            return false;
        }
    }
    
    #Function to check if list of unclosed tags has those, that require preservation of new lines
    private function hasToPreserve(array $openTags): bool
    {
        return $this->hasOpenTags($openTags, $this->preserveSpacesIn);
    }
    
    #Function to check if we have open wrapper tags
    private function hasOpenWrappers(array $openTags): bool
    {
        return $this->hasOpenTags($openTags, $this->wrapperOnly);
    }
    
    #Function to check if we have open wrapper tags
    private function hasOpenInsideWrappers(array $openTags): bool
    {
        return $this->hasOpenTags($openTags, $this->insideWrappersOnly);
    }
    
    #Generalized function to check if currently open tags has any tag from a list
    private function hasOpenTags(array $openTags, array $list): bool
    {
        #Get keys, which are the real tags
        $openTags = array_keys($openTags);
        #Iterrate through them
        foreach ($openTags as $tag) {
            #Check if it's in the list of tags, where we preserve the new lines
            if (in_array(strtolower($tag), $list)) {
                #Just 1 match is enough
                return true;
            }
        }
        return false;
    }
    
    #Function to check if string has non-phrasing content tags
    private function hasNonPhrasing(string $string): bool
    {
        #Check for tags, which are not phrasing content. Checking only for opening tags, since orphaned closing tags can trigger this,
        #but we do not care for them, since normally they won't break the HTML
        if (preg_match('/<(?!p|\/|'.implode('|', $this->phrasingContent).')[^>]*>/ui', $string) === 1) {
            return true;
        } else {
            return false;
        }
    }
    
    #Function to check if string has non-phrasing content tags
    private function hasNonFlow(string $string): bool
    {
        #Check for tags, which are not flow content. Checking only for opening tags, since orphaned closing tags can trigger this,
        #but we do not care for them, since normally they won't break the HTML
        if (preg_match('/<(?!p|\/|'.implode('|', $this->flowContent).')[^>]*>/ui', $string) === 1) {
            return true;
        } else {
            return false;
        }
    }
    
    #Function to check if string has unclosed tags
    private function hasUnclosedTags(string $string): array
    {
        #Get all opening tags. This will also match self-closing tags, if they have "/" at the end or do not use it at all
        preg_match_all('/<([a-zA-Z\-]+)(\s*\/?| [^<>]+)?>/ui', $string, $foundTags, PREG_PATTERN_ORDER);
        $openingTags = $foundTags[0];
        #Get all closing tags. This will also match self-closing tags, if they have "/" at the beginning
        preg_match_all('/<\/([a-zA-Z\-]+)\s*>/ui', $string, $foundTags, PREG_PATTERN_ORDER);
        $closingTags = $foundTags[0];
        #Remove all self-closing tags from opening tags
        foreach ($openingTags as $key=>$tag) {
            #Get the real tag name
            $tag = strtolower(preg_replace('/<([a-zA-Z\-]+)(\s*\/?| [^<>]+)?>/ui', '$1', $tag));
            #Check if self-closing
            if (in_array($tag, self::voidElements)) {
                #Remove from array
                unset($openingTags[$key]);
            } else {
                $openingTags[$key] = $tag;
            }
        }
        #Remove all self-closing tags from closing tags
        foreach ($closingTags as $key=>$tag) {
            #Get the real tag name
            $tag = strtolower(preg_replace('/<\/([a-zA-Z\-]+)\s*>/ui', '$1', $tag));
            #Check if self-closing
            if (in_array($tag, self::voidElements)) {
                #Remove from array
                unset($closingTags[$key]);
            } else {
                $closingTags[$key] = $tag;
            }
        }
        #Count unique tags
        $uniqueTags = [
            'opening' => array_count_values($openingTags),
            'closing' => array_count_values($closingTags),
        ];
        #Compare arrays and leave only tags, that are unmatched
        foreach ($uniqueTags['opening'] as $tag=>$count) {
            if (isset($uniqueTags['closing'][$tag]) && $uniqueTags['closing'][$tag] === $count) {
                unset($uniqueTags['opening'][$tag], $uniqueTags['closing'][$tag]);
            }
        }
        #Do the same for the closing tags, too. Not sure if we can get any "hits" on this cycle, but my gut feeling says, that we better check
        foreach ($uniqueTags['closing'] as $tag=>$count) {
            if (isset($uniqueTags['opening'][$tag]) && $uniqueTags['opening'][$tag] === $count) {
                unset($uniqueTags['closing'][$tag], $uniqueTags['opening'][$tag]);
            }
        }
        return $uniqueTags;
    }
    
    
    #######################
    # Getters and setters #
    #######################
    public function getPhrasingContent(): array
    {
        return $this->phrasingContent;
    }

    public function setPhrasingContent(array $phrasingContent): self
    {
        if (!empty($phrasingContent)) {
            $this->phrasingContent = array_merge($this->phrasingContent, $phrasingContent);
        }
        return $this;
    }
    
    public function getPreserveSpacesIn(): array
    {
        return $this->preserveSpacesIn;
    }
    
    public function setPreserveSpacesIn(array $preserveSpacesIn): self
    {
        $this->preserveSpacesIn = $preserveSpacesIn;
        return $this;
    }
    
    public function getWrapperOnly(): array
    {
        return $this->wrapperOnly;
    }
    
    public function setWrapperOnly(array $wrapperOnly): self
    {
        $this->wrapperOnly = $wrapperOnly;
        return $this;
    }
    
    #insideWrappersOnly
    public function getInsideWrappersOnly(): array
    {
        return $this->insideWrappersOnly;
    }
    
    public function setInsideWrappersOnly(array $insideWrappersOnly): self
    {
        $this->insideWrappersOnly = $insideWrappersOnly;
        return $this;
    }
    
    public function getSituationalBR(): bool
    {
        return $this->situationalBR;
    }
    
    public function setSituationalBR(bool $situationalBR): self
    {
        $this->situationalBR = $situationalBR;
        return $this;
    }
    
    public function getCollapseNewLines(): bool
    {
        return $this->collapseNewLines;
    }
    
    public function setCollapseNewLines(bool $collapseNewLines): self
    {
        $this->collapseNewLines = $collapseNewLines;
        return $this;
    }
}
