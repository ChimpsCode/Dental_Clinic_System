# Services Integration - Complete Guide

## What Was Fixed

### 1. Created `api_public_services.php`
This API returns all active services from database for staff admission page.

### 2. Updated `admin_services.php`
- Now handles add/edit/delete operations with simple PHP forms (no API needed)
- Shows all services including inactive ones
- Services marked as "Active" will appear in staff admission
- Added note explaining that only active services show in staff page

### 3. Updated `staff_new_admission.php`
- Added error handling for service loading
- Added "Refresh Services" button to manually reload services
- Added debugging console logs to track loading
- Better error messages when services fail to load

### 4. Created `test_services_integration.php`
Test file to verify everything works end-to-end.

## How It Works

### Admin Side (Adding Services)
1. Go to `admin_services.php`
2. Click "Add New Service"
3. Fill in:
   - Service Name (e.g., "Teeth Whitening")
   - Mode (BULK, SINGLE, or NONE)
   - Price (e.g., 5000)
   - Duration (optional, e.g., 60 minutes)
   - Description (optional)
   - Status: Active ✓
4. Click "Add Service"
5. Service is saved to database and immediately shows in the table

### Staff Side (Using Services)
1. Go to `staff_new_admission.php`
2. Services load automatically from database via API
3. Staff sees all ACTIVE services in Step 4 (Services section)
4. Patient can select services they want
5. Service pricing and mode are displayed

**If services don't load:**
- Click "🔄 Refresh Services" button at top of services section
- Check browser console (F12) for errors

## Testing the Integration

### Quick Test
1. Open: `http://localhost/Dental_Clinic_System/test_services_integration.php`
2. Click "Check Database" - should show active services count
3. Click "Test API" - should show API working
4. Click "Open Admin Services" - add a test service there
5. Come back and test again - count should increase
6. Click "Open Staff Admission" - should see all services

### Browser Console Debugging
Open `staff_new_admission.php` and press F12:
- Look for: `[SERVICES] Loaded:` - shows services data
- Look for: `[SERVICES] Rendering services` - shows rendering process
- Any errors will show in red

## Common Issues & Solutions

### Issue: "Loading services..." doesn't go away
**Solution:**
1. Check if `api_public_services.php` exists in root folder
2. Open browser console (F12) and check for errors
3. Test API directly in browser: `http://localhost/Dental_Clinic_System/api_public_services.php`
4. Should return JSON with services array

### Issue: Services show in admin but not in staff
**Solution:**
1. Check service status in admin - must be "Active"
2. Inactive services won't show in staff admission
3. Refresh staff admission page after adding new service

### Issue: Add service button in admin not working
**Solution:**
1. Check if browser blocks the modal
2. Try clicking "Add New Service" and look for any errors
3. All operations are handled on same page with PHP POST

### Issue: Can't edit or delete services
**Solution:**
1. Click "Edit" button - modal opens with current data
2. Modify fields and click "Update Service"
3. Click "Delete" - confirm dialog to delete

## Service Modes Explained

### BULK Mode
- Patient selects entire arches (upper/lower)
- Used for services like "Teeth Cleaning", "X-Ray Full Mouth"
- Staff sees arch selection buttons in dental chart

### SINGLE Mode
- Patient selects individual teeth
- Used for services like "Tooth Extraction", "Root Canal"
- Staff can click on specific teeth in 3D chart

### NONE Mode
- No tooth selection needed
- Used for services like "Consultation", "Prescription"
- Staff skips dental chart step

## File Locations

- `admin_services.php` - Admin can manage services
- `staff_new_admission.php` - Staff admission uses services
- `api_public_services.php` - Returns active services
- `test_services_integration.php` - Test the integration

## Database Table

Services are stored in `services` table:
- id (auto-increment)
- name (service name)
- mode (BULK, SINGLE, or NONE)
- price (decimal)
- duration_minutes (int)
- description (text)
- is_active (1 = active, 0 = inactive)
- created_at, updated_at (timestamps)
