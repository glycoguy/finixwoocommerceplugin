# Troubleshooting Guide - v1.6.2

## Error: "Application ID is missing"

### Symptom
Console shows: `Finix Subscriptions: Application ID is missing!`

### Cause
Application ID not configured in settings

### Solution
1. Go to: **WooCommerce → Settings → Payments → Finix Payment Gateway (Subscriptions)**
2. Scroll to **Test Application ID** field
3. Enter: `APrDvHNFTmhdNfPqU2Sx5juJ` (for sandbox)
4. Click **Save changes**
5. Refresh checkout page
6. Check console - should see "Finix.js initialized successfully"

---

## Error: "Cannot read properties of null (reading 'tokenize')"

### Symptom
Payment fails with this error in console

### Cause
Finix.js didn't load or initialize properly

### Solutions

#### Solution 1: Check Application ID
```
1. Settings → Payments → Finix Payment Gateway (Subscriptions)
2. Verify Test Application ID is filled in
3. Save settings
4. Refresh page
```

#### Solution 2: Check Finix.js Loading
```
1. Open browser console (F12)
2. Go to Network tab
3. Refresh checkout page
4. Search for "finix.js"
5. Should see 200 OK status
```

If Finix.js fails to load:
- Disable ad blockers
- Try different browser
- Check firewall settings
- Verify CDN accessible: https://cdn.finixpayments.com/v1/finix.js

#### Solution 3: Clear Cache
```
1. Clear browser cache: Ctrl+Shift+Delete
2. Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
3. Try again
```

---

## Error: "Payment system not ready"

### Symptom
User clicks "Place Order" and gets this message

### Cause
Finix hasn't finished initializing

### Solution
This usually means timing issue:

1. **Clear browser cache**
2. **Hard refresh page** (Ctrl+F5)
3. **Wait 2-3 seconds** after page loads before trying to pay
4. Check console for "Finix.js initialized successfully"

If problem persists:
```php
// Increase initialization timeout
// In finix-blocks.js, change:
const timer = setTimeout(init, 500);
// To:
const timer = setTimeout(init, 1000);
```

---

## Payment Form Doesn't Appear

### On Blocks Checkout (`/checkout-new`)

#### Check 1: Is WooCommerce Blocks Active?
```
Dashboard → Plugins → Search "WooCommerce Blocks"
Should be active
```

#### Check 2: Is Payment Method Enabled?
```
WooCommerce → Settings → Payments
Find "Finix Payment Gateway (Subscriptions)"
Should be enabled (slider ON)
```

#### Check 3: Check Console for Errors
```
F12 → Console tab
Look for red errors
```

Common errors:
- "Payment method not registered" → Blocks integration issue
- "Script failed to load" → File missing or path wrong
- "Finix is not defined" → Finix.js not loaded

### On Classic Checkout (`/checkout`)

Should work if:
- Plugin is activated
- Payment method is enabled
- No JavaScript errors

---

## Error: "Unexpected token '<'"

### Symptom
AJAX call fails with HTML error

### Cause
WordPress returning error page instead of JSON

### Solutions

#### Solution 1: Check PHP Errors
```
1. Enable WordPress debug mode
2. Check wp-content/debug.log
3. Look for PHP errors
```

#### Solution 2: Check AJAX URL
```
Console → Network tab → XHR filter
Look at failed request
Check response - if HTML, it's an error page
```

#### Solution 3: Check Nonce
```
If seeing "Nonce verification failed":
1. Log out and log back in
2. Clear cookies
3. Try again
```

---

## Gateway ID Conflict

### Symptom
Two Finix gateways showing same settings

### Cause
Old gateway still active with ID `finix`

### Solution
v1.6.2 uses new ID `finix_subscriptions` to avoid this!

If you still see conflict:
1. Deactivate both plugins
2. Delete old Finix plugin completely
3. Install only v1.6.2
4. Activate

---

## Bank Account Not Working

### Symptom
Bank account option not appearing or failing

### Solutions

#### Check 1: Payment Type Selector
```
Should see radio buttons:
○ Credit Card
○ Bank Account (EFT)
```

If not visible:
- Check JavaScript console for errors
- Verify blocks JS loaded
- Refresh page

#### Check 2: PAD Agreement
```
User must check PAD agreement checkbox
If unchecked, will get error:
"You must agree to pre-authorized debits"
```

#### Check 3: Canadian Account Format
```
Institution Number: 3 digits (e.g., 001)
Transit Number: 5 digits (e.g., 12345)
Account Number: 7-12 digits
```

---

## Subscription Not Creating

### Symptom
Order completes but no subscription created

### Checks

#### Check 1: Is Product a Subscription?
```
Products → Edit product
Should see "Subscription" tab
Subscription settings must be configured
```

#### Check 2: WooCommerce Subscriptions Active?
```
Plugins → Installed Plugins
"WooCommerce Subscriptions" must be active
```

#### Check 3: Check Order Notes
```
WooCommerce → Orders → View order
Check order notes for Finix messages
Look for subscription ID
```

#### Check 4: Check Finix Dashboard
```
Log into Finix Dashboard
Check if subscription was created
Compare with WooCommerce subscription ID
```

---

## Receipt Description Not Saving

### Symptom
Customer enters description but it doesn't save

### Cause
Field not being passed to order

### Solution
1. Check if field appears on checkout
2. Enter text in "Receipt Description" field
3. Complete order
4. Check order in admin
5. Look for meta key `_finix_custom_description`

If missing:
- Check JavaScript console for errors
- Verify blocks JS is loaded
- Try classic checkout to isolate issue

---

## Console Errors Reference

### Good (Working)
```
✅ Finix.js initialized successfully {applicationId: "APr...", environment: "sandbox"}
✅ Finix Blocks payment method registered successfully
✅ Payment setup started
✅ Processing credit card payment
✅ Tokenizing card...
✅ Card tokenized successfully: TKxxx...
```

### Bad (Not Working)
```
❌ Finix Subscriptions: Application ID is missing!
❌ Failed to initialize Finix.js
❌ Finix.js library not loaded!
❌ Payment processing error: TypeError
❌ AJAX error: ...
```

---

## Plugin Won't Activate

### Error: "Plugin could not be activated"

#### Check 1: PHP Version
```
Need PHP 7.4 or higher
Dashboard → Tools → Site Health → Info → Server
```

#### Check 2: WordPress Version  
```
Need WordPress 5.8 or higher
Dashboard → Updates
```

#### Check 3: WooCommerce
```
Must be installed and active
Plugins → Installed Plugins
```

#### Check 4: WooCommerce Subscriptions
```
Must be installed and active
Without it, plugin won't activate
```

#### Check 5: PHP Errors
```
Check error log: wp-content/debug.log
Look for:
- Fatal error
- Parse error
- Class not found
```

---

## Settings Not Saving

### Symptom
Enter settings, click Save, they don't save

### Solutions

#### Solution 1: Check Permissions
```
Settings must be writable
Check wp-content/uploads permissions
Should be 755 or 775
```

#### Solution 2: Check for Errors
```
After clicking Save:
1. Look for red error messages
2. Check browser console
3. Check network tab for failed requests
```

#### Solution 3: Try Different Field
```
1. Change just Title field
2. Save
3. If Title saves but API keys don't
   → Might be validation issue
```

---

## Testing Best Practices

### Always Test In This Order

1. **Plugin Activation**
   - Should activate without errors
   - Check plugins page shows "Active"

2. **Settings Page**
   - Should load without errors
   - Should see all fields including Application ID

3. **Console Check**
   - Open F12 before loading checkout
   - Go to checkout page
   - Verify Finix.js loads and initializes

4. **Form Display**
   - Should see payment method option
   - Should see card fields or bank fields
   - Should see receipt description field (if subscription)

5. **Test Payment**
   - Use test card: 4111 1111 1111 1111
   - Complete order
   - Check order status
   - Check subscription created

### Test Cards

#### Working Test Cards
```
Visa: 4111 1111 1111 1111
Mastercard: 5555 5555 5555 4444
Amex: 3782 822463 10005
```

All use:
- Expiry: Any future date (e.g., 12/28)
- CVV: Any 3-4 digits (e.g., 123)

#### Failing Test Cards (For Error Testing)
```
Card Declined: 4000 0000 0000 0002
Insufficient Funds: 4000 0000 0000 9995
```

---

## Getting More Help

### Check Logs

#### WordPress Debug Log
```
Location: /wp-content/debug.log

Enable debug mode:
wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### WooCommerce Logs
```
WooCommerce → Status → Logs
Look for:
- finix-api log
- finix-webhooks log
- fatal-errors log
```

#### Browser Console
```
F12 → Console tab
Copy all errors and warnings
```

### Information to Provide

When asking for help, include:
1. **Exact error message** (copy/paste)
2. **Browser console output** (screenshot)
3. **WordPress version**
4. **WooCommerce version**
5. **PHP version**
6. **Active plugins list**
7. **What you were trying to do**
8. **Steps that led to the error**

---

## Common Mistakes

### Mistake 1: Using Live Credentials in Test Mode
```
❌ Wrong:
Test Mode: ON
Test Application ID: APxxxxLive

✅ Correct:
Test Mode: ON
Test Application ID: APrDvHNFTmhdNfPqU2Sx5juJ
```

### Mistake 2: Not Clearing Cache
```
After any change:
1. Clear browser cache
2. Hard refresh (Ctrl+F5)
3. Test again
```

### Mistake 3: Wrong Checkout Page
```
❌ Testing on: /checkout (classic)
When problem is on: /checkout-new (blocks)

Make sure you're testing the right page!
```

### Mistake 4: Ad Blocker Interference
```
Ad blockers can block:
- Finix.js CDN
- AJAX requests
- Payment forms

Test with ad blocker disabled
```

---

## Emergency Recovery

### If Plugin Breaks Site

1. **Via FTP/cPanel:**
```
Navigate to: /wp-content/plugins/
Rename folder: finix-woocommerce-subscriptions
To: finix-woocommerce-subscriptions-disabled
```

2. **Via Database:**
```sql
UPDATE wp_options 
SET option_value = 'a:0:{}' 
WHERE option_name = 'active_plugins';
```

3. **Via WP-CLI:**
```bash
wp plugin deactivate finix-woocommerce-subscriptions
```

---

**Remember:** When in doubt, check the console! Most issues show clear error messages in the browser console (F12 → Console tab).
