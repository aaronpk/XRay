XRay
====

XRay parses structured content from a URL.


## Discovering Content

XRay will parse content in the following formats. First the URL is checked against known services:

* Twitter
* GitHub
* XKCD
* Hackernews

If the contents of the URL is XML or JSON, then XRay will parse the Atom, RSS or JSONFeed formats.

Finally, XRay looks for Microformats on the page and will determine the content from that.

* h-card
* h-entry
* h-event
* h-review
* h-recipe
* h-product
* h-item
* h-feed


## Library

XRay can be used as a library in your PHP project. The easiest way to install it and its dependencies is via composer.

```
composer require p3k/xray
```

You can also [download a release](https://github.com/aaronpk/XRay/releases) which is a zip file with all dependencies already installed.

### Parsing

```php
$xray = new p3k\XRay();
$parsed = $xray->parse('https://aaronparecki.com/2017/04/28/9/');
```

If you already have an HTML or JSON document you want to parse, you can pass it as a string in the second parameter.

```php
$xray = new p3k\XRay();
$html = '<html>....</html>';
$parsed = $xray->parse('https://aaronparecki.com/2017/04/28/9/', $html);
```

```php
$xray = new p3k\XRay();
$jsonfeed = '{"version":"https://jsonfeed.org/version/1","title":"Manton Reece", ... ';
// Note that the JSON document must be passed in as a string in this case
$parsed = $xray->parse('https://manton.micro.blog/feed.json', $jsonfeed);
```

In both cases, you can add an additional parameter to configure various options of how XRay will behave. Below is a list of the options.

* `timeout` - The timeout in seconds to wait for any HTTP requests
* `max_redirects` - The maximum number of redirects to follow
* `include_original` - Will also return the full document fetched
* `target` - Specify a target URL, and XRay will first check if that URL is on the page, and only if it is, will continue to parse the page. This is useful when you're using XRay to verify an incoming webmention.
* `expect=feed` - If you know the thing you are parsing is a feed, include this parameter which will avoid running the autodetection rules and will provide better results for some feeds.

Additional parameters are supported when making requests that use the Twitter or GitHub API. See the Authentication section below for details.

```php
$xray = new p3k\XRay();

$parsed = $xray->parse('https://aaronparecki.com/2017/04/28/9/', [
  'timeout' => 30
]);

$parsed = $xray->parse('https://aaronparecki.com/2017/04/28/9/', $html, [
  'target' => 'http://example.com/'
]);
```

The `$parsed` return value will look like the below. See "Primary Data" below for an explanation of the vocabularies returned.

```
$parsed = Array
(
    [data] => Array
        (
            [type] => card
            [name] => Aaron Parecki
            [url] => https://aaronparecki.com/
            [photo] => https://aaronparecki.com/images/profile.jpg
        )

    [url] => https://aaronparecki.com/
    [code] => 200,
    [source-format] => mf2+html
)
```

### Processing Microformats2 JSON

If you already have a parsed Microformats2 document as an array, you can use a special function to process it into XRay's native format. Make sure you pass the entire parsed document, not just the single item.

```php
$html = '<div class="h-entry"><p class="p-content p-name">Hello World</p><img src="/photo.jpg"></p></div>';
$mf2 = Mf2\parse($html, 'http://example.com/entry');

$xray = new p3k\XRay();
$parsed = $xray->process('http://example.com/entry', $mf2); // note the use of `process` not `parse`

Array
(
    [data] => Array
        (
            [type] => entry
            [post-type] => photo
            [photo] => Array
                (
                    [0] => http://example.com/photo.jpg
                )

            [content] => Array
                (
                    [text] => Hello World
                )

        )

    [url] => http://example.com/entry

    [source-format] => mf2+json
)
```


### Rels

You can also use XRay to fetch all the rel values on a page, merging the list of HTTP `Link` headers with rel values with the HTML rel values on the page.

```php
$xray = new p3k\XRay();
$rels = $xray->rels('https://aaronparecki.com/');
```

This will return a similar response to the parser, but instead of a `data` key containing the parsed page, there will be `rels`, an associative array. Each key will contain an array of all the values that match that rel value.

```
Array
(
    [url] => https://aaronparecki.com/
    [code] => 200
    [rels] => Array
        (
            [hub] => Array
                (
                    [0] => https://switchboard.p3k.io/
                )

            [authorization_endpoint] => Array
                (
                    [0] => https://aaronparecki.com/auth
                )
            ...
```


### Feed Discovery

You can use XRay to discover the types of feeds available at a URL.

```php
$xray = new p3k\XRay();
$feeds = $xray->feeds('http://percolator.today');
```

This will fetch the URL, check for a Microformats feed, as well as check for rel=alternates pointing to Atom, RSS or JSONFeed URLs. The response will look like the below.

```
Array
(
    [url] => https://percolator.today/
    [code] => 200
    [feeds] => Array
        (
            [0] => Array
                (
                    [url] => https://percolator.today/
                    [type] => microformats
                )

            [1] => Array
                (
                    [url] => https://percolator.today/podcast.xml
                    [type] => rss
                )

        )

)
```

### Customizing the User Agent

To set a unique user agent, (some websites will require a user agent be set), you can set the `http` property of the object to a `p3k\HTTP` object.

```php
$xray = new p3k\XRay();
$xray->http = new p3k\HTTP('MyProject/1.0.0 (http://example.com/)');
$xray->parse('http://example.com/');
```


## API

XRay can also be used as an API to provide its parsing capabilities over an HTTP service.

To parse a page and return structured data for the contents of the page, simply pass a url to the `/parse` route.

```
GET /parse?url=https://aaronparecki.com/2016/01/16/11/
```

To conditionally parse the page after first checking if it contains a link to a target URL, also include the target URL as a parameter. This is useful when using XRay to verify an incoming webmention.

```
GET /parse?url=https://aaronparecki.com/2016/01/16/11/&target=http://example.com
```

In both cases, the response will be a JSON object containing a key of "type". If there was an error, "type" will be set to the string "error", otherwise it will refer to the kind of content that was found at the URL, most often "entry".

You can also make a POST request with the same parameter names.

If you already have an HTML or JSON document you want to parse, you can include that in the POST parameter `body`. This POST request would look like the below:

```
POST /parse
Content-type: application/x-www-form-urlencoded

url=https://aaronparecki.com/2016/01/16/11/
&body=<html>....</html>
```

or for Twitter/GitHub where you might have JSON,

```
POST /parse
Content-type: application/x-www-form-urlencoded

url=https://github.com/aaronpk/XRay
&body={"repo":......}
```

### Parameters

XRay accepts the following parameters when calling `/parse`

* `url` - the URL of the page to parse
* `target` - Specify a target URL, and XRay will first check if that URL is on the page, and only if it is, will continue to parse the page. This is useful when you're using XRay to verify an incoming webmention.
* `timeout` - The timeout in seconds to wait for any HTTP requests
* `max_redirects` - The maximum number of redirects to follow
* `include_original` - Will also return the full document fetched
* `expect=feed` - If you know the thing you are parsing is a feed, include this parameter which will avoid running the autodetection rules and will provide better results for some feeds.


### Authentication

If the URL you are fetching requires authentication, include the access token in the parameter "token", and it will be included in an "Authorization" header when fetching the URL. (It is recommended to use a POST request in this case, to avoid the access token potentially being logged as part of the query string.) This is useful for [Private Webmention](https://indieweb.org/Private-Webmention) verification.

```
POST /parse

url=https://aaronparecki.com/2016/01/16/11/
&target=http://example.com
&token=12341234123412341234
```


### API Authentication

XRay uses the Twitter and Github APIs to fetch posts, and those API require authentication. In order to keep XRay stateless, it is required that you pass in the credentials to the parse call.

You should only send the credentials when the URL you are trying to parse is a Twitter URL or a GitHub URL, so you'll want to check for whether the hostname is `twitter.com`, `github.com`, etc. before you include credentials in this call.


#### Twitter Authentication

XRay uses the Twitter API to fetch Twitter URLs. You can register an application on the Twitter developer website, and generate an access token for your account without writing any code, and then use those credentials when making an API request to XRay.

* `twitter_api_key` - Your application's API key
* `twitter_api_secret` - Your application's API secret
* `twitter_access_token` - Your Twitter access token
* `twitter_access_token_secret` - Your Twitter secret access token


#### GitHub Authentication

XRay uses the GitHub API to fetch GitHub URLs, which provides higher rate limits when used with authentication. You can pass a GitHub access token along with the request and XRay will use it when making requests to the API.

* `github_access_token` - A GitHub access token


### Error Response

```json
{
  "error": "not_found",
  "error_description": "The URL provided was not found"
}
```

Possible errors are listed below:

* `not_found`: The URL provided was not found. (Returned 404 when fetching)
* `ssl_cert_error`: There was an error validating the SSL certificate. This may happen if the SSL certificate has expired.
* `ssl_unsupported_cipher`: The web server does not support any of the SSL ciphers known by the service.
* `timeout`: The service timed out trying to connect to the URL.
* `invalid_content`: The content at the URL was not valid. For example, providing a URL to an image will return this error.
* `no_link_found`: The target link was not found on the page. When a target parameter is provided, this is the error that will be returned if the target could not be found on the page.
* `no_content`: No usable content could be found at the given URL.
* `unauthorized`: The URL returned HTTP 401 Unauthorized.
* `forbidden`: The URL returned HTTP 403 Forbidden.

### Response Format

```json
{
  "data":{
    "type":"entry",
    "post-type":"photo",
    "published":"2017-03-01T19:00:33-08:00",
    "url":"https://aaronparecki.com/2017/03/01/14/hwc",
    "category":[
      "indieweb",
      "hwc"
    ],
    "photo":[
      "https://aaronparecki.com/2017/03/01/14/photo.jpg"
    ],
    "syndication":[
      "https://twitter.com/aaronpk/status/837135519427395584"
    ],
    "content":{
      "text":"Hello from Homebrew Website Club PDX! Thanks to @DreamHost for hosting us! 🍕🎉 #indieweb",
      "html":"Hello from Homebrew Website Club PDX! Thanks to <a href=\"https://twitter.com/DreamHost\">@DreamHost</a> for hosting us! <a href=\"https://aaronparecki.com/emoji/%F0%9F%8D%95\">🍕</a><a href=\"https://aaronparecki.com/emoji/%F0%9F%8E%89\">🎉</a> <a href=\"https://aaronparecki.com/tag/indieweb\">#indieweb</a>"
    },
    "author":{
      "type":"card",
      "name":"Aaron Parecki",
      "url":"https://aaronparecki.com/",
      "photo":"https://aaronparecki.com/images/profile.jpg"
    }
  },
  "url":"https://aaronparecki.com/2017/03/01/14/hwc",
  "code":200,
  "source-format":"mf2+html"
}
```

#### Primary Data

The primary object on the page is returned in the `data` property. This will indicate the type of object (e.g. `entry`), and will contain the vocabulary's properties that it was able to parse from the page.

* `type` - the Microformats 2 vocabulary found for the primary object on the page, without the `h-` prefix (e.g. `entry`, `event`)
* `post-type` - only for "posts" (e.g. not for `card`s) - the [Post Type](https://www.w3.org/TR/post-type-discovery/) of the post (e.g. (`note`, `photo`, `reply`))

If a property supports multiple values, it will always be returned as an array. The following properties support multiple values:

* `in-reply-to`
* `like-of`
* `repost-of`
* `bookmark-of`
* `follow-of`
* `syndication`
* `photo` (of an entry, not of a card)
* `video`
* `audio`
* `category`

The content will be an object that always contains a "text" property and may contain an "html" property if the source documented published HTML content. The "text" property must always be HTML escaped before displaying it as HTML, as it may include unescaped characters such as `<` and `>`.

The author will always be set in the entry if available. The service follows the [authorship discovery](https://indieweb.org/authorship) algorithm to try to find the author information elsewhere on the page if it is not inside the entry in the source document.

All URLs provided in the output are absolute URLs. If the source document contains a relative URL, it will be resolved first.

#### Post Type Discovery

XRay runs the [Post Type Discovery](https://www.w3.org/TR/post-type-discovery/) algorithm and also includes a `post-type` property.

The following post types are returned, which are slightly expanded from what is currently documented by the Post Type Discovery spec.

* `event`
* `recipe`
* `review`
* `rsvp`
* `repost`
* `like`
* `reply`
* `bookmark`
* `follow`
* `checkin`
* `video`
* `audio`
* `photo`
* `article`
* `note`


#### Other Properties

Other properties are returned in the response at the same level as the `data` property.

* `url` - The effective URL that the document was retrieved from. This will be the final URL after following any redirects.
* `code` - The HTTP response code returned by the URL. Typically this will be 200, but if the URL returned an alternate HTTP code that also included an h-entry (such as a 410 deleted notice with a stub h-entry), you can use this to find out that the original URL was actually deleted.
* `source-format` - Indicates the format of the source URL that was used to generate the parsed result. Possible values are:
  * `mf2+html`
  * `mf2+json`
  * `feed+json`
  * `xml`
  * `github`/`xkcd`

#### Feeds

XRay can return information for several kinds of feeds. The URL (or body) passed to XRay will be checked for the following formats:

* XML (Atom and RSS)
* JSONFeed (https://jsonfeed.org)
* Microformats [h-feed](https://indieweb.org/h-feed)

If the page being parsed represents a feed, then the response will look like the following:

```json
{
  "data": {
    "type": "feed",
    "items": [
      {...},
      {...}
    ]
  }
}
```

Each object in the `items` array will contain a parsed version of the item, in the same format that XRay normally returns. When parsing Microformats feeds, the [authorship discovery](https://indieweb.org/authorship) will be run for each item to build out the author info.

Atom, RSS and JSONFeed will all be normalized to XRay's vocabulary, and only recognized properties will be returned.

## Rels API

There is also an API method to parse and return all rel values on the page, including HTTP `Link` headers and HTML rel values.

```
GET /rels?url=https://aaronparecki.com/
```

See [above](#rels) for the response format.

## Feed Discovery API

```
GET /feeds?url=https://aaronparecki.com/
```

See [above](#feed-discovery) for the response format.


## Token API

When verifying [Private Webmentions](https://indieweb.org/Private-Webmention#How_to_Receive_Private_Webmentions), you will need to exchange a code for an access token at the token endpoint specified by the source URL.

XRay provides an API that will do this in one step. You can provide the source URL and code you got from the webmention, and XRay will discover the token endpoint, and then return you an access token.

```
POST /token

source=http://example.com/private-post
&code=1234567812345678
```

The response will be the response from the token endpoint, which will include an `access_token` property, and possibly an `expires_in` property.

```
{
  "access_token": "eyJ0eXAXBlIjoI6Imh0dHB8idGFyZ2V0IjoraW0uZGV2bb-ZO6MV-DIqbUn_3LZs",
  "token_type": "bearer",
  "expires_in": 3600
}
```

If there was a problem fetching the access token, you will get one of the errors below in addition to the HTTP related errors returned by the parse API:

* `no_token_endpoint` - Unable to find an HTTP header specifying the token endpoint.


## Installation

### From Source

```
# Clone this repository

git clone git@github.com:aaronpk/XRay.git
cd XRay

# Install dependencies
composer install
```

### From Zip Archive

* Download the latest release from https://github.com/aaronpk/XRay/releases
* Extract to a folder on your web server


### Web Server Configuration

Configure your web server to point to the `public` folder.

Make sure all requests are routed to `index.php`. XRay ships with `.htaccess` files for Apache. For nginx, you'll need a rule like the following in your server config block.

```
  try_files $uri /index.php?$args;
```

