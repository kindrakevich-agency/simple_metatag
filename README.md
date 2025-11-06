# Drupal 11 simple metatag module.

1. In taxonomy and node edit forms add fileds: title and description. If filled - add title,description,og:title,og:description tags to html header. If not - generate tags from title and body. Tag og:image must be set from field_image if exist and set. Take first image only. If field image does not exist in taxonomy, get this field from children latest node (sort by date desc). Here is full tags list:
<title>Your Page Title Here</title>
<meta name="description" content="A concise summary of your page's content, typically 150-160 characters.">
<meta property="og:title" content="The Title You Want on Social Media">
<meta property="og:description" content="The description you want on social media.">
<meta property="og:image" content="https://www.your-website.com/images/your-share-image.jpg">
<meta property="og:url" content="https://www.your-website.com/your-page-url">

2. Add admin section for add metatags by path: fields: path,domain (domains list checkboxes),language,title,description,image. Must accept any path and must override taxonomy or node metatags. Must accept <front>.

3. Rewrite this readme after module creation.
