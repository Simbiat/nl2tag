# nl2tag
Class to convert new lines to various HTML tags: `br`, `p` and `li`. It can also help with creating nice-looking changelogs (based on `li` wrapping).

## Why?
Initially the idea was for a version of `nl2br`, but for paragraphs. If you google (or [Stackoverflow](https://stackoverflow.com/questions/3738124/nl2br-for-paragraphs)) for it, you can find several easy approaches for this, but all of those that I found have at least some flaws:
1. They do not check whether there is already a `p` tag around respective paragraph. This can result in extra paragraphs. At least some browsers will then show this as extra paragraphs, meaning `<p>line1<p>line2</p>line3</p>` will result in 3 paragraphs, which may not be the intention.
2. In fact, there is a bunch of tags, that are not expected inside of `p`, as per the [spec](https://html.spec.whatwg.org/#phrasing-content) of `phrasing content`. Or rather there is a limited set of tags, tha can be.
3. They will change new lines inside tags, where you want to preserve new lines as is. `pre` and `textarea` are the ones, where you could generally want that. `code`, `samp`, `kbd` and `var` are an example of other common values, but technically it can be any tag with `white-space` CSS property set to either `pre`, `pre-wrap`, `pre-line` or `break-spaces`.  
4. They usually only check for `\r\n` or just `\r` or `\n`, while there are actually more symbols, that would mean new line, and they also have respective HTML entities, which can easily occur in HTML string.

When you are working with HTML, these flaws can become an issue, thus I wanted to find a way to circumvent them, hence this library. But at early stage of conceptualization, I realized, that it would make sense to also do something similar for `li` items and even `br`.  
I also wanted to be able to create lists for changelogs, that could be styled nicely with CSS, and since that would imply the same logic as we have for `nl2li` it's done in the same class.

## Limitations
1. Performance, obviously, since the processing is trying to be "smart", has various checks here and there, they do take time. It will be slower than other solutions, although most likely you will not notice it, still.
2. No recursion. Meaning, that, if you have something line
```html
text
<div>text2
text3</div>
text4
```
You will get
```html
<p>text</p><div>text2<br>text3</div><p>text4</p>
```
And not
```html
<p>text</p><div><p>text2</p><p>text3</p></div><p>text4</p>
```
This is because recursion would require traversal of the DOM tree, which implies conversion into `DOMDocument`, but converting a string can add extra tags in quite unpredictable places, as it tries to "fix" the potentially broken document. Such modification can result in vastly different results from what would expect, and can't be predicted. Considering expected use of the library, using only 1 level should be enough.
3. Tags like `area`, `link`, `main` and `meta` will not be wrapped, even though they are valid phrasing (for `p`) or flow (for `li`) content, because they aer **conditionally** valid, and I do not believe it's worth to overcomplicate the logic for them, considering the expected use of the library. If required, they can be included through respective settings, though.
4. I am sure there is some combination of tags and/or new lines that will result in "corrupt" output, if processed by this class, since it relies on regex. Alternative would be to rely on PHP's `DOMDocument` and pals.

## Constants
Class has some public constants, in case you would require something similar in other classes in your code:  
1. `newLinesRegex` - list of entities, that are considered "new lines". It's a string with `|` as separator to be used directly in regex.  
2. `voidElements` - array of standard HTML5 tags, that are considered "self-closing", that is do not require a closing tag.  
3. `preserveSpacesIn` - array of standard HTML5 tags, that usually imply preservation of whitespace in them.  
4. `phrasingContent` - array of standard HTML5 tags, that are considered as "phrasing content" and do not have any additional conditions for that.  
5. `flowContent` - similar tp `phrasingContent`, but for "flow content" as per [spec](https://html.spec.whatwg.org/#flow-content).

## Options
The class has some options, that can be changed by respective setters (or checked by respective getters):
1. `$preserveSpacesIn` (`setPreserveSpacesIn()`, `getPreserveSpacesIn()`) - use to adjust list of tags inside which you want to preserve spaces. Can be an empty array, which will mean, that new lines inside all tags may be replaced accordingly.
2. `$phrasingContent` (`setPhrasingContent()`, `getPhrasingContent()`) - use to adjust the list of tags, that are considered as phrasing content. Tags from respective constant cannot be removed. The most likely scenario for updating this list is when you have custom HTML elements.
3. `$flowContent` (`setFlowgContent()`, `getFlowContent()`) - use to adjust the list of tags, that are considered as flow content. Tags from respective constant cannot be removed. The most likely scenario for updating this list is when you have custom HTML elements.
4. `$wrapperOnly` (`setWrapperOnly()`, `getWrapperOnly()`) - list of tags, that are treated as wrappers only, like `table` and the like. They can have new lines between tags, and unless excluded, you can get excessive `<br>` tags, if `$situationalBR` is turned on.
5. `$insideWrappersOnly` (`setInsideWrappersOnly()`, `getInsideWrappersOnly()`) - list of tags, that only happen within respective wrappers and can have meaningful new lines in them, like `td`, `th`. Used specifically in conjunction with `$wrapperOnly` setting.
6. `$situationalBR` (`setSituationalBR()`, `getSituationalBR()`) - boolean flag (default is `true`) indicating, whether class can replace newlines with `<br>` when a new line is found inside the content of the tag, and it's not the one where we preserve whitespace. Sorry if the name is not clear enough, but was not able to come up with something else more proper.
7. `$collapseNewLines` (`setCollapseNewLines()`, `getCollapseNewLines()`) - boolean flag (default is `true`) indicating, whether class will try to collapse new lines. This means that `<br><br>` will be replaced with `<br>`, `<br>` tags between `<p>` tags will be removed (that is **only** `<p>1</p><br><p>2</p>` will change into `<p>1</p><p>2</p>`) and paragraphs consisting only of whitespace will be removed, too. I believe, that in most cases, you would want this enabled, but there may be situations, when you would like to avoid it, for example, when you can have respective combinations inside `<textarea>`.
8. `$preserveNonBreakingSpace` (`setPreserveNonBreakingSpace()`, `getPreserveNonBreakingSpace()`) - boolean flag, indicating, whether class will preserve empty paragraphs with non-breaking space (`&nbsp`, which gets converted to proper character during processing). If set to `true` (default is `false`), lines like `<p>&nbsp</p>` will not be removed, even if `$collapseNewLines` is also `true`.

## Usage
Create the object:
```php
$object = (new \Simbiat\nl2tag);
```
Adjust settings, if required. Setters can be chained. Below example will add `custom-element` as phrasing content and set that only `textarea` needs to preserve whitespace.
```php
$object->setPhrasingContent(['custom-element'])->setPreserveSpacesIn(['textarea']);
```
Call appropriate function, while sending a string to it.
```php
$result = $object->nl2p('string');
```
```html
<div>asdasda</div>
<a href="https://example.com">asdsada</a>
test
test2
<code class="test">
test3<div>afdas</div></code>
<code>tgitjsglsjdgdsjglsd</code>
<span>span</span> after_span
<div class="test">test4</div>
<p>test5</p>
<div>test6
test7</div>
```
passed through `nl2p()` will provide this result:
```html
<div>asdasda</div><p><a href="https://example.com">asdsada</a></p><p>test</p><p>test2</p><code class="test">
test3<div>afdas</div></code><p><code>tgitjsglsjdgdsjglsd</code></p><p><span>span</span> after_span</p><div class="test">test4</div><p>test5</p><div>test6<br>test7</div>
```
If passed through `nl2br()`, it will provide this result:
```html
<div>asdasda</div><br><a href="https://example.com">asdsada</a><br>test<br>test2<br><code class="test">
test3<div>afdas</div></code><br><code>tgitjsglsjdgdsjglsd</code><br><span>span</span> after_span<br><div class="test">test4</div><br><p>test5</p><br><div>test6<br>test7</div>
```
If passed through `nl2li()`, it will provide this result:
```html
<ul><li><div>asdasda</div></li><li><a href="">asdsada</a></li><li>test</li><li>test2</li><li><code class="test">
test3<div>afdas</div></code></li><li><code>tgitjsglsjdgdsjglsd</code></li><li><span>span</span> after_span</li><li><div class="test">test4</div></li><li><p>test5</p></li><li><div>test6<br>test7</div></li></ul>
```
`nl2li()` wraps string in `ul` by default, but you can pass a second optional string (`menu`, `ol`, `ul`), which will allow to change the type of list you get.  
There is also a variation of `nl2li()`, called `changelog()`. Logic is very similar, but it also checks for the 1st character in text value of a line and then adds appropriate class to `<li>`. If character is not one of `*`, `+` or `-` a sublist (that is new `<ul>`) will be started until another line like that or the end of string will be encountered.  
A string like this:
```html
* Some general change in logic
List of changes in a specific module:
+ Feature added
- Feature removed
List of changes in another module:
* Some general change
```
Will result in HTML like this (whitespace added between tags for readability, real string will not have them):
```html
<ul class="changelog_list">
    <li class="changelog_change">Some general change in logic</li>
    <li class="changelog_sublist_name">List of changes in a specific module:</li>
    <ul class="changelog_sublist">
        <li class="changelog_addition">Feature added</li>
        <li class="changelog_removal">Feature removed</li>
    </ul>
    <li class="changelog_sublist_name">List of changes in another module:</li>
    <ul class="changelog_sublist">
        <li class="changelog_change">Some general change</li>
    </ul>
</ul>
```
If styled, you can get a nice-looking changelog, for example, like what you see [here](https://www.simbiat.dev/talks/posts/123).
