<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($entries as $entry)
  <url>
    <loc>{{ $entry['loc'] }}</loc>
@if (!empty($entry['lastmod']))
    <lastmod>{{ $entry['lastmod'] }}</lastmod>
@endif
    <changefreq>{{ $entry['changefreq'] ?? 'weekly' }}</changefreq>
    <priority>{{ $entry['priority'] ?? '0.8' }}</priority>
@if (!empty($entry['image']) && !empty($entry['image']['loc']))
    <image:image>
      <image:loc>{{ $entry['image']['loc'] }}</image:loc>
@if (!empty($entry['image']['title']))
      <image:title>{{ $entry['image']['title'] }}</image:title>
@endif
    </image:image>
@endif
  </url>
@endforeach
</urlset>
