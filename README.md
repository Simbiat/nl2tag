# nl2tag
Class to convert new lines to various HTML tags: `br`, `p` and `li`.

## Why?
Initially the idea was for a version of `nl2br`, but for paragraphs. If you google (or [Stackoverflow](https://stackoverflow.com/questions/3738124/nl2br-for-paragraphs)) for it, you can find several easy approaches for this, but all of those that I found have at least some flaws:
1. They do not check whether there is already a `p` tag around respective paragraph. This can result in extra paragraphs. At least some browsers will then show this as extra paragraphs, meaning `<p>line1<p>line2</p>line3</p>` will result in 3 paragraphs, which may not be the intention.
2. In fact, there is a bunch of tags, that are not expected inside of `p`, as per the [spec](https://html.spec.whatwg.org/#phrasing-content) of `phrasing content`. Or rather there is a limited set of tags, tha can be.
3. They will change new lines inside tags, where you want to preserve new lines as is. `pre` and `textarea` are the ones, where you could generally want that. `code`, `samp`, `kbd` and `var` are an example of other common values, but technically it can be any tag with `white-space` CSS property set to either `pre`, `pre-wrap`, `pre-line` or `break-spaces`.  

When you are working with HTML, these flaws can become an issue, thus I wanted to find a way to circumvent them, hence this library. But at early stage of conceptualization, I realized, that it would make sense to also do something similar for `li` items and even `br`.

`$preservedIn` - list of HTML tags, where you want to preserve new lines as is. 
