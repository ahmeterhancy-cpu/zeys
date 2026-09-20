<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($girdiler as $girdi)
    <url>
        <loc>{{ $girdi['loc'] }}</loc>
@if (! empty($girdi['lastmod']))
        <lastmod>{{ $girdi['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $girdi['changefreq'] }}</changefreq>
        <priority>{{ $girdi['priority'] }}</priority>
    </url>
@endforeach
</urlset>
