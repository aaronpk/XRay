XRay
====


## Discovering Content

The contents of the URL is checked in the following order:

* A supported silo URL (coming soon)
* h-entry, h-event, h-card
* OEmbed (coming soon)
* OGP (coming soon)


## API

To parse a page and return structured data for the contents of the page, simply pass a url to the parse route.

```
GET /parse?url=https://aaronparecki.com/2016/01/16/11/
```

To conditionally parse the page after first checking if it contains a link to a target URL, also include the target URL as a parameter. This is useful if using XRay to verify an incoming webmention.

```
GET /parse?url=https://aaronparecki.com/2016/01/16/11/&target=http://poetica.com
```

In both cases, the response will be a JSON object containing a key of "type". If there was an error, "type" will be set to the string "error", otherwise it will refer to the kind of content that was found at the URL, most often "entry".

### Error Response

```json
{
  "error": "not_found",
  "error_description": "The URL provided was not found"
}
```

Other possible errors are listed below:

* not_found: The URL provided was not found. (Returned 404 when fetching)
* ssl_cert_error: There was an error validating the SSL certificate. This may happen if the SSL certificate has expired.
* ssl_unsupported_cipher: The web server does not support any of the SSL ciphers known by the service.
* timeout: The service timed out trying to connect to the URL.
* invalid_content: The content at the URL was not valid. For example, providing a URL to an image will return this error.
* no_link_found: The target link was not found on the page. When a target parameter is provided, this is the error that will be returned if the target could not be found on the page.
* no_content: No usable content could be found at the given URL.

## Response Format

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

