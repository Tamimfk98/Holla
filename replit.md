# eSports Tournament Website

A complete tournament management system for gaming tournaments with admin panel and user features.

## Project Overview

This is a PHP-based eSports tournament website featuring:
- Tournament creation and management (Admin)
- User registration and match participation
- Screenshot upload for match results
- Payment integration (bKash, Nagad)
- Tournament brackets and scoring

## Project Architecture

### Database
- **Engine**: SQLite (for development)
- **Location**: `database/esports_tournament.db`
- **Tables**: users, tournaments, tournament_registrations, matches, match_screenshots, payments, wallet_transactions, notifications, admins
- **Auto-initialization**: Schema creates automatically on first run

### File Structure
- `/admin/` - Admin panel for tournament management
- `/user/` - User interface for players
- `/config/` - Database and site configuration
- `/assets/` - CSS, JS, and uploaded images
- `/includes/` - Helper functions

### Two-Tier Winner Selection System
**This is a key architectural feature of the tournament system:**

1. **Match-Level Winners** (Individual Match Results)
   - Location: `admin/results.php`
   - Process: Users upload match screenshots → Admin reviews and selects match winners
   - Purpose: Determines winners of individual matches for bracket progression
   - Updates: match.winner_id, scores, match status

2. **Tournament-Level Champions** (Final Tournament Results)
   - Location: `admin/tournament_results.php` 
   - Process: Admin selects final tournament champions (1st, 2nd, 3rd place)
   - Purpose: Determines overall tournament winners who receive prize money
   - Updates: tournament.winner_id, runner_up_id, third_place_id + automatic wallet distribution

### Key Features
1. **Admin Panel**
   - Tournament CRUD operations with status management
   - Thumbnail upload and display
   - User management with wallet tracking
   - Match management with round support
   - Two-tier winner selection system
   - Payment and withdrawal approval

2. **User Panel**
   - Tournament browsing and registration
   - Match viewing and screenshot upload
   - Profile management with wallet balance
   - Withdrawal request system

## Recent Changes (August 20, 2025)

✓ **CRITICAL FIX: Application Startup Issue Resolved**
- Fixed workflow configuration error (was trying to run npm on PHP project)
- Created `server.php` custom routing script for PHP development server
- Added `start.sh` startup script for development environment
- Configured "PHP Server" workflow to properly serve application on port 5000
- Application now runs successfully with all features working

## Previous Changes (August 19, 2025)

✓ **Fixed Database Connection Issue**
- Converted from MySQL to SQLite for development
- Added automatic schema initialization
- Updated admin password hash to user-provided hash

✓ **Enhanced Admin Panel Thumbnail Upload**
- Added thumbnail column to tournaments table
- Fixed thumbnail display in admin list view
- Improved file upload validation
- Added current thumbnail preview in edit form

✓ **Improved User Screenshot Upload**
- Enhanced screenshot upload form visibility
- Added inline upload form in match detail view
- Improved file path handling for screenshot display
- Added visual feedback for upload status
- Made upload button more prominent with clear labeling

✓ **Enhanced Index Page**
- Added comprehensive tournaments section showing all tournaments
- Created "How Our Website Works" section with 4-step process
- Added gaming community links (Facebook, Telegram, Instagram)
- Implemented recent winners showcase
- Added professional footer with links and information
- Improved thumbnail handling with fallback placeholders

✓ **Complete Database Compatibility Resolution**
- Fixed all remaining NOW() functions to CURRENT_TIMESTAMP in all files
- Resolved schema.sql compatibility issues for SQLite
- Added missing database tables: payments, matches, match_screenshots, admins, wallet_transactions
- Added missing columns: status, is_admin, created_at, updated_at, scheduled_date
- Fixed all SQLite syntax compatibility issues across the entire codebase

✓ **Comprehensive Prize & Withdrawal System**
- Added 3-tier prize system (Winner, Runner-up, Third Place)
- Implemented automatic wallet balance management
- Created withdrawal request system with admin approval
- Added notifications for tournament results and withdrawals
- Enhanced database schema with wallet_balance column
- Built admin tournament results management page
- Created user withdrawal interface with payment method selection

✓ **Enhanced Tournament Management**
- Added prize distribution fields to tournament creation
- Built tournament results publication system
- Automatic prize money distribution to user wallets
- Results button in admin tournament list
- Support for multiple prize tiers with custom amounts

✓ **User Dashboard Enhancements**
- Added wallet balance display
- Integrated withdrawal money navigation
- Real-time balance updates after tournament wins
- Comprehensive user statistics display

✓ **Admin System Complete**
- Complete admin panel functionality restored
- Admin user management with status tracking
- Payment processing and approval system
- Tournament and match management
- Withdrawal request processing
- All admin features fully operational

✓ **Two-Tier Winner Selection System Operational**
- Match-level winners: Users upload screenshots, admin selects individual match winners
- Tournament-level champions: Admin selects final tournament winners (1st, 2nd, 3rd place)
- Automatic prize distribution to tournament champions' wallets
- Clear separation between match progression and final tournament results
- Complete notification system for both match and tournament winners

## User Preferences

- Language: English
- Focus: Fix existing functionality rather than adding new features
- Priority: User experience and admin usability

## Environment

- **Server**: PHP built-in server
- **Port**: 5000
- **Database**: SQLite (development)
- **Admin Login**: admin@esports.com / admin123

## Technical Notes

### Upload Directories
- Thumbnails: `assets/images/tournaments/`
- Screenshots: `assets/images/screenshots/`

### Security Features
- CSRF token protection
- File type validation
- File size limits (5MB)
- SQL injection prevention with PDO

### File Upload Improvements Made
1. **Admin Thumbnails**: Fixed display in table view with proper image tags
2. **User Screenshots**: Added prominent upload form directly in match detail view
3. **File Paths**: Corrected relative paths for proper image display
4. **Upload Feedback**: Enhanced visual indicators for upload status

## Deployment Notes

The website is ready for testing with:
- Working database connection
- Functional admin panel
- Enhanced screenshot upload system
- Proper file upload handling