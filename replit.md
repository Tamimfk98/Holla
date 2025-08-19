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
- **Tables**: users, tournaments, tournament_registrations, matches, match_screenshots
- **Auto-initialization**: Schema creates automatically on first run

### File Structure
- `/admin/` - Admin panel for tournament management
- `/user/` - User interface for players
- `/config/` - Database and site configuration
- `/assets/` - CSS, JS, and uploaded images
- `/includes/` - Helper functions

### Key Features
1. **Admin Panel**
   - Tournament CRUD operations
   - Thumbnail upload and display
   - User management
   - Match management

2. **User Panel**
   - Tournament browsing and registration
   - Match viewing and screenshot upload
   - Profile management

## Recent Changes (August 19, 2025)

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

✓ **Database Schema**
- Created complete table structure
- Added foreign key relationships
- Implemented proper indexing
- Added screenshot tracking table

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