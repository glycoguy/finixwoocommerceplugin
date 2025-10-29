# Quick Installation Guide - v1.6.2

## ⚡ 5-Minute Setup

### Before You Start
Have ready:
- Your Finix Application ID (Test: `APrDvHNFTmhdNfPqU2Sx5juJ`)
- Your Finix API credentials
- WordPress admin access

---

## Step 1: Remove Old Version (1 minute)

1. **Dashboard** → **Plugins**
2. Find "Finix WooCommerce Subscriptions"
3. Click **Deactivate**
4. Click **Delete**
5. Confirm deletion

⚠️ Your settings are saved in the database - they won't be lost!

---

## Step 2: Install v1.6.2 (2 minutes)

1. **Plugins** → **Add New** → **Upload Plugin**
2. Click **Choose File**
3. Select: `finix-woocommerce-subscriptions-v1.6.2.zip`
4. Click **Install Now**
5. Wait for upload...
6. Click **Activate Plugin**

✅ Plugin should activate without errors!

---

## Step 3: Configure (2 minutes)

1. **WooCommerce** → **Settings** → **Payments**
2. Find: **Finix Payment Gateway (Subscriptions)**
3. Click **Manage**
4. Configure these settings:

### Essential Settings
- ✅ **Enable/Disable:** Check "Enable Finix Payment Gateway"
- ✅ **Test mode:** Check "Enable Test Mode"  
- ✅ **Test Application ID:** `APrDvHNFTmhdNfPqU2Sx5juJ`
- ✅ **Test API Key:** (your sandbox API key)
- ✅ **Test API Secret:** (your sandbox API secret)
- ✅ **Test Merchant ID:** (your sandbox merchant ID)

### Optional Settings
- Title: "Credit Card (Finix Subscriptions)"
- Description: "Pay securely using your credit card. Supports subscriptions and one-time payments."
- Subscriptions Only: Check if you only want this for subscriptions

5. Click **Save changes**

---

## Step 4: Test (Quick Check)

### Open Browser Console
Press **F12** (or Cmd+Option+I on Mac)

### Go to Checkout
Visit: `https://yoursite.com/checkout-new`

### Check Console
Look for these messages:
```
✅ Finix.js initialized successfully
✅ Finix Blocks payment method registered
```

If you see these, it's working!

---

## Step 5: Test Payment (Optional)

1. Add a subscription product to cart
2. Go to checkout
3. Select "Credit Card (Finix Subscriptions)"
4. Fill in test card:
   - **Card:** `4111 1111 1111 1111`
   - **Expiry:** `12/28`
   - **CVV:** `123`
5. Fill in billing details
6. Optional: Add receipt description
7. Click **Place Order**

### Expected Result
- ✅ Order completes
- ✅ Redirects to thank you page
- ✅ Order shows "Processing" status
- ✅ Subscription created

---

## 🎯 Quick Reference

### Gateway Details
- **ID:** `finix_subscriptions` (changed from `finix`)
- **Class:** `WC_Gateway_Finix_Subscriptions`
- **Version:** 1.6.2

### Settings Location
```
WooCommerce → Settings → Payments → Finix Payment Gateway (Subscriptions)
```

### Console Check
Press F12, look for:
- "Finix.js initialized successfully"
- "Finix Blocks payment method registered"

### Test Card
```
Number: 4111 1111 1111 1111
Expiry: 12/28
CVV: 123
```

---

## ❌ If Something Goes Wrong

### Plugin Won't Activate
- Check WordPress version (need 5.8+)
- Check PHP version (need 7.4+)
- Check WooCommerce is active
- Check WooCommerce Subscriptions is active

### Application ID Missing Error
- Go to Settings
- Scroll to "Test Application ID"
- Enter: `APrDvHNFTmhdNfPqU2Sx5juJ`
- Save settings

### Finix.js Not Loading
- Check console for network errors
- Disable ad blockers
- Try different browser
- Check if CDN is accessible: `https://cdn.finixpayments.com/v1/finix.js`

### Still Getting Errors
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+F5)
3. Deactivate all other plugins
4. Try with default WordPress theme
5. Check error log: `wp-content/debug.log`

---

## 📞 Need Help?

Check these files in the plugin:
- `CRITICAL-FIX-v1.6.2.md` - Complete explanation
- `TROUBLESHOOTING.md` - Detailed troubleshooting

Check your error log:
```
/wp-content/debug.log
```

---

## ✅ Success Checklist

- [ ] Old plugin removed
- [ ] v1.6.2 installed and activated
- [ ] Settings configured
- [ ] Test Application ID entered
- [ ] Console shows Finix.js initialized
- [ ] Payment form appears on checkout
- [ ] Test order completes successfully

All checked? You're good to go! 🎉

---

**Time:** ~5 minutes  
**Difficulty:** Easy  
**Result:** Working blocks checkout with Finix.js tokenization!
