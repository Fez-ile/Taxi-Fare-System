#Taxi Fare System

A web-based platform that helps commuters in Pretoria check taxi fares before traveling and contribute updated fare information to improve accuracy for everyone.

## Overview

The Taxi Fare Information System is designed to solve the lack of accessible and reliable fare data for minibus taxis. It allows users to:
- Search for fares between locations
- View long-distance routes
- Report new or updated fares

The system also includes an admin interface for managing routes, taxi ranks, and reviewing submitted fare data.

---

##  Problem

Commuters in Pretoria often do not know taxi fares before traveling, leading to:
- Uncertainty in transport costs
- Difficulty planning trips
- Inconsistent pricing information

---

##  Solution

This application provides a centralized, user-friendly platform where:
- Users can instantly check fares between locations
- The community can report fare updates
- Admins can verify and manage fare data

---

##  Features

### Fare Lookup
- Search fares using "From" and "To" locations
- Supports bidirectional routes (A → B = B → A)

###  Fare Reporting
- Submit new or updated fares
- Dual input:
  - Select from dropdowns
  - Or manually enter locations

###  Admin Dashboard
- Manage routes and taxi ranks
- Secure CRUD operations using prepared statements
- Prevent deletion of ranks linked to routes

###  Modern UI/UX
- Clean, responsive design (mobile-first)
- Tab-based navigation (no page reloads)
- Smooth user experience

###  Security & Validation
- Input validation for all forms
- Duplicate route prevention
- Session-based authentication for admin access

---

## Technologies Used

- **Backend:** PHP (mysqli, prepared statements, sessions)
- **Frontend:** HTML, CSS3, JavaScript
- **Database:** MySQL
- **Version Control:** Git & GitHub

---

##  UX Highlights

- Responsive design for mobile and desktop
- Green-themed UI for clarity and accessibility
- Tab-based interface (Pretoria Taxis / Report Fare)
- No unnecessary page navigation
