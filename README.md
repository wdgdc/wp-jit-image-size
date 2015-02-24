# JIT Image Sizes

JIT (Just in Time) Image Sizes is a WordPress Plugin that tell WordPress not to create a thumbnail for every size that is registered, but only create it whenever it is requested by the front end of the site.

When thumbnails are (re) generated via upload, another plugin or wp-cli, they are actually deleted and re-generated again the next time they are requested.  This is ideal for large sites with a large amount of image assets in the media library.  

**This plugin is still in development and is not recommended for use on production sites**
