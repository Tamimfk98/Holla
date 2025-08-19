# eSports Tournament Management System

## Overview

This is an eSports Tournament Management System designed to handle competitive gaming tournaments. The system provides functionality for managing tournaments, matches, payments, and user interactions with a modern gaming-themed interface. The application appears to be a web-based platform built with a focus on user experience for the gaming community.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **Technology Stack**: HTML, CSS, and vanilla JavaScript
- **Styling Framework**: Custom CSS with CSS variables for theming
- **UI Framework**: Bootstrap (indicated by navbar classes)
- **Design Pattern**: Component-based initialization with page-specific modules
- **Theme**: Gaming-focused dark theme with neon accents and gaming fonts (Orbitron, Rajdhani)

### JavaScript Architecture
- **Module Pattern**: Uses page-specific initialization functions
- **Event-Driven**: DOM-ready event listeners for component initialization
- **Configuration Management**: Global CONFIG object for application settings
- **Component System**: Modular components (tooltips, modals, forms, file uploads, notifications)

### Key Components
- **Dashboard**: Main overview interface
- **Tournament Management**: Tournament creation and management
- **Match System**: Match scheduling and results tracking
- **Payment Processing**: Financial transaction handling
- **File Upload**: Media and document upload functionality
- **Admin Panel**: Administrative controls and management

### Form Handling
- **Validation**: Client-side form validation system
- **AJAX Forms**: Asynchronous form submission
- **File Uploads**: Dedicated file upload handling with size limits (5MB)

### User Interface
- **Responsive Design**: Mobile-friendly interface
- **Gaming Aesthetics**: Dark theme with cyan/blue accent colors
- **Interactive Elements**: Tooltips, modals, and notifications
- **Auto-dismiss Alerts**: 5-second timeout for user notifications

## External Dependencies

### Frontend Libraries
- **Bootstrap**: UI component framework (evidenced by navbar classes)
- **Google Fonts**: Custom gaming fonts (Orbitron, Rajdhani, Roboto)

### Potential Integrations
- **Payment Gateway**: Based on payments module, likely integrates with payment processors
- **File Storage**: File upload system suggests cloud storage integration
- **Database**: Backend database for tournament, match, and user data management

### Browser APIs
- **File API**: For file upload functionality
- **Fetch/XMLHttpRequest**: For AJAX communications
- **DOM API**: For dynamic content manipulation