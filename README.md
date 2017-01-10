XRay
====


## Discovering Content

The contents of the URL is checked in the following order:

* A silo URL from one of the following websites:
** Instagram
** Twitter
** (more coming soon)
* h-entry, h-event, h-card
* OEmbed (coming soon)
* OGP (coming soon)


## Parse API

To parse a page and return structured data for the contents of the page, simply pass a url to the parse route.

```
GET /parse?url=https://aaronparecki.com/2016/01/16/11/
```

To conditionally parse the page after first checking if it contains a link to a target URL, also include the target URL as a parameter. This is useful if using XRay to verify an incoming webmention.

```
GET /parse?url=https://aaronparecki.com/2016/01/16/11/&target=http://example.com
```

In both cases, the response will be a JSON object containing a key of "type". If there was an error, "type" will be set to the string "error", otherwise it will refer to the kind of content that was found at the URL, most often "entry".

You can also make a POST request with the same parameter names.

### Authentication

If the URL you are fetching requires authentication, include the access token in the parameter "token", and it will be included in an "Authorization" header when fetching the URL. (It is recommended to use a POST request in this case, to avoid the access token potentially being logged as part of the query string.)

```
POST /parse

url=https://aaronparecki.com/2016/01/16/11/
&target=http://example.com
&token=12341234123412341234
```

### Twitter Authentication

XRay uses the Twitter API to fetch posts, and the Twitter API requires authentication. In order to keep XRay stateless, it is required that you pass in Twitter credentials to the parse call. You can register an application on the Twitter developer website, and generate an access token for your account without writing any code, and then use those credentials when making an API request to XRay.

You should only send Twitter credentials when the URL you are trying to parse is a Twitter URL, so you'll want to check for whether the hostname is `twitter.com` before you include credentials in this call.

* twitter_api_key - Your application's API key
* twitter_api_secret - Your application's API secret
* twitter_access_token - Your Twitter access token
* twitter_access_token_secret - Your Twitter secret access token


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
  "data": {
    "type": "entry",
    "author": {
    	"type": "card",
    	"name": "Aaron Parecki",
    	"photo": "https://aaronparecki.com/images/aaronpk-256.jpg",
    	"url": "https://aaronparecki.com/"
    },
    "url": "https://aaronparecki.com/2016/01/16/11/",
    "published": "2016-01-16T16:26:43-08:00",
    "photo": [
      "https://aaronparecki.com/2016/01/16/11/photo.png"
    ],
    "syndication": [
      "https://twitter.com/aaronpk/status/688518372170977280"
    ],
    "summary": "Now that @MozillaPersona is shutting down, the only good way to do email-based login is how @poetica does it.",
    "content": {
      "html": "Now that <a href=\"https://twitter.com/MozillaPersona\">@MozillaPersona</a> is shutting down, the only good way to do email-based login is how <a href=\"https://twitter.com/poetica\">@poetica</a> does it.",
      "text": "Now that @MozillaPersona is shutting down, the only good way to do email-based login is how @poetica does it."
    },
  }
}
```

If a property supports multiple values, it will always be returned as an array. The following properties support multiple values:

* in-reply-to
* syndication
* photo (of entry, not of a card)

The content will be an object that always contains a "text" property and may contain an "html" property if the source documented published HTML content. The "text" property must always be HTML escaped before displaying it as HTML, as it may include unescaped characters such as `<` and `>`.

The author will always be set in the entry if available. The service follows the [authorship discovery](http://indiewebcamp.com/authorship) algorithm to try to find the author information elsewhere on the page if it is not inside the entry in the source document.

All URLs provided in the output are absolute URLs. If the source document contains a relative URL, it will be resolved first.

In a future version, replies, likes, reposts, etc. of this post will be included if they are listed on the page.

```json
{
  "data": {
    "type": "entry",
    ...
    "like": [
      {
        "type": "cite",
        "author": {
          "type": "card",
          "name": "Thomas Dunlap",
          "photo": "https://s3-us-west-2.amazonaws.com/aaronparecki.com/twitter.com/9055c458a67762637c0071006b16c78f25cb610b224dbc98f48961d772faff4d.jpeg",
          "url": "https://twitter.com/spladow"
        },
        "url": "https://twitter.com/aaronpk/status/688518372170977280#favorited-by-16467582"
      }
    ],
    "comment": [
      {
        "type": "cite",
        "author": {
          "type": "card",
          "name": "Poetica",
          "photo": "https://s3-us-west-2.amazonaws.com/aaronparecki.com/twitter.com/192664bb706b2998ed42a50a860490b6aa1bb4926b458ba293b4578af599aa6f.png",
          "url": "http://poetica.com/"
        },
        "url": "https://twitter.com/poetica/status/689045331426803712",
        "published": "2016-01-18T03:23:03-08:00",
        "content": {
          "text": "@aaronpk @mozillapersona thanks very much! :)"
        }
      }
    ]
  }
}

```

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


