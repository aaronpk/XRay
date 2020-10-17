<?php
namespace p3k\XRay;

class PostType {

  // Takes an XRay format post and runs post-type-discovery, returning a single string
  // https://www.w3.org/TR/post-type-discovery/
  public static function discover($post) {

    // A few of the post types are defined as the same as their microformats h-* types
    if(in_array($post['type'], ['event','recipe','review']))
      return $post['type'];

    if(isset($post['rsvp']))
      return 'rsvp';

    if(isset($post['repost-of']))
      return 'repost';

    if(isset($post['like-of']))
      return 'like';

    if(isset($post['in-reply-to']))
      return 'reply';

    if(isset($post['bookmark-of']))
      return 'bookmark';

    if(isset($post['follow-of']))
      return 'follow';

    if(isset($post['checkin']))
      return 'checkin';

    if(isset($post['video']))
      return 'video';

    if(isset($post['audio']))
      return 'audio';

    if(isset($post['photo']))
      return 'photo';

    $content = '';
    if(isset($post['content']))
      $content = $post['content']['text'];
    elseif(isset($post['summary']))
      $content = $post['summary'];

    if(!isset($post['name']) || !trim($post['name']))
      return 'note';

    // Trim all leading/trailing whitespace
    $name = trim($post['name']);

    // Collapse all sequences of internal whitespace to a single space (0x20) character each
    $name = preg_replace('/\s+/', ' ', $name);
    $content = preg_replace('/\s+/', ' ', $content);

    // If this processed "name" property value is NOT a prefix of the
    // processed "content" property, then it is an article post.
    if(strpos($content, $name) !== 0) {
      return 'article';
    }

    return 'note';
  }

}
