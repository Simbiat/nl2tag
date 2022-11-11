<?php
declare(strict_types=1);
namespace Simbiat;

use function Twig\Tests\html;

class nl2tag
{
    #Common tags, in which you may want to preserve the new lines
    private array $preserveIn = ['pre', 'textarea', 'code', 'samp', 'kbd', 'var'];
    #Tags, that are allowed in `p`, except for `area`, `link` and `meta`, may be included under certain conditions.
    #Add them manually along with any other custom tags, if you know that they can be in the piece of text you are parsing.
    private array $phrasingContent = ['a', 'abbr', 'audio', 'b', 'bdi', 'bdo', 'br', 'button', 'canvas', 'cite', 'code', 'data', 'datalist',
                                        'del', 'dfn', 'em', 'embed', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'map', 'mark', 'math', 'meter',
                                        'noscript', 'object', 'output', 'picture', 'progress', 'q', 'ruby', 's', 'samp', 'script', 'select', 'slot', 'small',
                                        'span', 'strong', 'sub', 'sup', 'svg', 'template', 'textarea', 'time', 'u', 'var', 'video', 'wbr'];
    #Original string before any modification
    private string $originalString = '';
    #Split string
    private array $splitString = [];
    #List of found tags
    private array $foundTags = ['preserveIn' => ['opening' => [], 'closing' => []], 'notPhrasing' => ['opening' => [], 'closing' => []]];
    
    public function __construct(string $string, array $preserveIn = ['pre', 'textarea', 'code', 'samp', 'kbd', 'var'], array $phrasingContent = [])
    {
        $this->preserveIn = $preserveIn;
        if (!empty($phrasingContent)) {
            $this->phrasingContent = array_merge($this->phrasingContent, $phrasingContent);
        }
        $this->originalString = $string;
        $this->getTags();
        $this->splitString = $this->split();
    }
    
    public function nl2br(): string
    {
        return '';
    }
    
    public function nl2p(): string
    {
        $newString = '';
        foreach ($this->splitString as $part) {
            #Check if the part is already wrapped in <p>
            if (preg_match('/^<p[^>]*>.*<\/p>$/uis', $part) === 1) {
                #Add a string as is
                $newString .= $part;
            } else {
                #Need to check for opening tags, where we preserve spaces
                
                #Need to check for tags, that are not allowed in paragraphs. Need to have both opening and closing parts
                
                $newString .= '<p>'.$part.'</p>';
            }
        }
        return $newString;
    }
    
    public function nl2li(): string
    {
        return '';
    }
    
    #Function to split the string into pieces
    private function split(): array
    {
        $parts = preg_split('/\R/ui', $this->originalString);
        if (!is_array($parts)) {
            return [];
        } else {
            return $parts;
        }
    }
    
    #Function to check for certain HTML tags
    private function getTags(): void
    {
        #Check if we have tags, that need new lines preservation. First we get the count of open ones
        preg_match_all('/<(p|'.implode('|', $this->preserveIn).')[^>]*>/ui', $this->originalString, $foundTags, PREG_PATTERN_ORDER);
        $this->foundTags['preserveIn']['opening'] = $foundTags[0];
        #Now closing ones
        preg_match_all('/<\/(p|'.implode('|', $this->preserveIn).')[^>]*>/ui', $this->originalString, $foundTags, PREG_PATTERN_ORDER);
        $this->foundTags['preserveIn']['closing'] = $foundTags[0];
        #Check for tags, which are not phrasing content
        preg_match_all('/<(?!p|\/|'.implode('|', $this->phrasingContent).')[^>]*>/ui', $this->originalString, $foundTags, PREG_PATTERN_ORDER);
        $this->foundTags['notPhrasing']['opening'] = $foundTags[0];
        #Closing tags
        preg_match_all('/<\/(?!p|'.implode('|', $this->phrasingContent).')[^>]*>/ui', $this->originalString, $foundTags, PREG_PATTERN_ORDER);
        $this->foundTags['notPhrasing']['opening'] = $foundTags[0];
    }
}
