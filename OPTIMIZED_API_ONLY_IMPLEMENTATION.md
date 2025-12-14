# Optimized API-Only Implementation Guide

## Overview

This implementation provides a **pure API-only approach** that:
- ✅ **Never saves to database** (no CPT posts, no images downloaded)
- ✅ **Minimal storage** (only transients that auto-expire)
- ✅ **Fast performance** (caches vessel IDs and individual vessel data)
- ✅ **Always up-to-date** (short cache times: 1-6 hours)
- ✅ **Supports single vessel pages** (via query parameters)

## Storage Comparison

### Current CPT Approach:
- **7,000 vessels × 1 featured image** = ~7-15GB (with thumbnails)
- **Database storage** = ~2-5MB
- **Total: ~10-20GB**

### API-Only Approach:
- **Transients only** = ~50-100MB (auto-expire, temporary)
- **No images downloaded** = 0GB
- **Total: ~50-100MB** (99% reduction!)

## How It Works

### 1. Vessel ID List Caching
- Fetches all vessel IDs once
- Caches for **6 hours**
- Lightweight (~50KB for 7,000 IDs)
- Auto-refreshes daily

### 2. Individual Vessel Data Caching
- Fetches full vessel data only when needed
- Caches each vessel for **1 hour**
- Only caches vessels that are viewed
- Auto-expires (no manual cleanup needed)

### 3. No Image Downloads
- All images use external URLs from YATCO
- No storage used for images
- Faster (no download time)

## Implementation Steps

### Step 1: Include the API-Only Module

Add to `yatco-custom-integration.php`:

```php
// Include API-only mode (optional - can be enabled/disabled)
require_once YATCO_PLUGIN_DIR . 'includes/yatco-api-only.php';
```

### Step 2: Modify Shortcode to Use API-Only Mode

The shortcode will automatically use API-only when `cache="no"` is set, but we can optimize it further.

### Step 3: Create Virtual Single Vessel Pages

Create a system that displays single vessels using query parameters instead of CPT posts.

## Benefits

1. **Storage**: 99% reduction (50-100MB vs 10-20GB)
2. **Performance**: Fast (cached, but always fresh)
3. **Maintenance**: No sync process needed
4. **Up-to-date**: Data refreshes every 1-6 hours automatically
5. **Scalable**: Works with any number of vessels

## Trade-offs

1. **Slightly slower** than CPT (but still fast with caching)
2. **No individual URLs** for SEO (but can be added with rewrite rules)
3. **Requires API access** (but you already have this)

## Next Steps

I can:
1. ✅ Modify the shortcode to use the optimized API-only functions
2. ✅ Create virtual single vessel pages
3. ✅ Add rewrite rules for SEO-friendly URLs
4. ✅ Add admin setting to enable/disable API-only mode

Would you like me to proceed with the full implementation?

