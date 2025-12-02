# Hair Salon Booking System - Complete Implementation Guide

## Overview
This document contains the complete implementation details for the hair salon booking feature with RBAC, calendar interface, double-booking prevention, and async email confirmations.

## Database Entities Created

### 1. Stylist Entity (`src/Entity/Stylist.php`)
- Fields: name, bio, photoUrl, specialization, isActive
- Relationship: OneToMany with Booking

### 2. Service Entity (`src/Entity/Service.php`)
- Fields: name, description, durationMinutes, price, isActive
- Relationship: OneToMany with Booking

### 3. Booking Entity (`src/Entity/Booking.php`)
- Fields: user, stylist, service, bookingDate, status, notes, createdAt, updatedAt
- Statuses: pending, confirmed, cancelled, completed
- Index on (booking_date, stylist_id) for performance

### 4. User Entity (existing)
- Already has roles support for RBAC (ROLE_USER, ROLE_ADMIN)

## Async Email System

### Message (`src/Message/BookingConfirmationEmail.php`)
- Contains booking ID for processing

### Handler (`src/MessageHandler/BookingConfirmationEmailHandler.php`)
- Fetches booking details
- Formats confirmation email
- Sends via Redis async queue
- Logs email delivery

### Configuration (`config/packages/messenger.yaml`)
```yaml
App\Message\BookingConfirmationEmail: async
```

## Controllers

### BookingController (`src/Controller/BookingController.php`)
**Routes:**
- GET `/booking/` - Main booking page
- GET `/booking/api/available-slots` - Get available time slots
- POST `/booking/create` - Create new booking with double-booking prevention
- POST `/booking/cancel/{id}` - Cancel user's booking

**Key Features:**
- Transaction-based double-booking prevention
- Real-time slot availability checking
- Async email dispatch after successful booking
- Ownership verification for cancellations

### BookingAdminController (`src/Controller/Admin/BookingAdminController.php`)
**Routes:**
- GET `/admin/bookings/` - Admin dashboard
- GET `/admin/bookings/calendar` - Calendar JSON API
- POST `/admin/bookings/{id}/status` - Update booking status
- GET `/admin/bookings/statistics` - Monthly statistics

**RBAC:** All routes require `ROLE_ADMIN`

## Frontend Templates Required

### User Templates

#### `templates/booking/index.html.twig`
```twig
{% extends 'base.html.twig' %}

{% block title %}Book an Appointment{% endblock %}

{% block body %}
<div class="container mt-5">
    <h1>Book Your Hair Appointment</h1>

    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>New Booking</h3>
                </div>
                <div class="card-body">
                    <form id="bookingForm">
                        <div class="mb-3">
                            <label for="stylist" class="form-label">Select Stylist</label>
                            <select class="form-select" id="stylist" name="stylist_id" required>
                                <option value="">Choose a stylist...</option>
                                {% for stylist in stylists %}
                                    <option value="{{ stylist.id }}" data-specialization="{{ stylist.specialization }}">
                                        {{ stylist.name }} - {{ stylist.specialization }}
                                    </option>
                                {% endfor %}
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="service" class="form-label">Select Service</label>
                            <select class="form-select" id="service" name="service_id" required>
                                <option value="">Choose a service...</option>
                                {% for service in services %}
                                    <option value="{{ service.id }}"
                                            data-duration="{{ service.durationMinutes }}"
                                            data-price="{{ service.price }}">
                                        {{ service.name }} - ${{ service.price }} ({{ service.durationMinutes }} min)
                                    </option>
                                {% endfor %}
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="bookingDate" class="form-label">Select Date</label>
                            <input type="date" class="form-control" id="bookingDate" name="booking_date"
                                   min="{{ 'now'|date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="bookingTime" class="form-label">Available Times</label>
                            <div id="timeSlots" class="d-grid gap-2">
                                <p class="text-muted">Select a stylist, service, and date to see available times</p>
                            </div>
                            <input type="hidden" id="selectedTime" name="booking_time">
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div id="bookingSummary" class="alert alert-info d-none">
                            <h5>Booking Summary</h5>
                            <p id="summaryText"></p>
                        </div>

                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            Book Appointment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Your Upcoming Bookings</h3>
                </div>
                <div class="card-body">
                    {% if userBookings is empty %}
                        <p class="text-muted">No upcoming bookings</p>
                    {% else %}
                        {% for booking in userBookings %}
                            <div class="card mb-2">
                                <div class="card-body">
                                    <h6>{{ booking.service.name }}</h6>
                                    <p class="mb-1">
                                        <strong>Date:</strong> {{ booking.bookingDate|date('M d, Y') }}<br>
                                        <strong>Time:</strong> {{ booking.bookingDate|date('g:i A') }}<br>
                                        <strong>Stylist:</strong> {{ booking.stylist.name }}
                                    </p>
                                    <button class="btn btn-sm btn-danger cancel-booking"
                                            data-id="{{ booking.id }}">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        {% endfor %}
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bookingForm');
    const stylistSelect = document.getElementById('stylist');
    const serviceSelect = document.getElementById('service');
    const dateInput = document.getElementById('bookingDate');
    const timeSlotsContainer = document.getElementById('timeSlots');
    const selectedTimeInput = document.getElementById('selectedTime');
    const submitBtn = document.getElementById('submitBtn');
    const summaryDiv = document.getElementById('bookingSummary');
    const summaryText = document.getElementById('summaryText');

    function loadAvailableSlots() {
        const stylistId = stylistSelect.value;
        const serviceId = serviceSelect.value;
        const date = dateInput.value;

        if (!stylistId || !serviceId || !date) {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Select all fields above to see available times</p>';
            return;
        }

        timeSlotsContainer.innerHTML = '<p class="text-muted">Loading available slots...</p>';

        fetch(`/booking/api/available-slots?stylist_id=${stylistId}&service_id=${serviceId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.slots && data.slots.length > 0) {
                    timeSlotsContainer.innerHTML = '';
                    data.slots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-outline-primary';
                        btn.textContent = slot.display;
                        btn.dataset.time = slot.time;
                        btn.onclick = function() {
                            document.querySelectorAll('#timeSlots button').forEach(b => {
                                b.classList.remove('active', 'btn-primary');
                                b.classList.add('btn-outline-primary');
                            });
                            this.classList.remove('btn-outline-primary');
                            this.classList.add('active', 'btn-primary');
                            selectedTimeInput.value = this.dataset.time;
                            updateSummary();
                            submitBtn.disabled = false;
                        };
                        timeSlotsContainer.appendChild(btn);
                    });
                } else {
                    timeSlotsContainer.innerHTML = '<p class="text-warning">No available slots for this date</p>';
                }
            })
            .catch(error => {
                console.error('Error loading slots:', error);
                timeSlotsContainer.innerHTML = '<p class="text-danger">Error loading available slots</p>';
            });
    }

    function updateSummary() {
        const stylistName = stylistSelect.options[stylistSelect.selectedIndex].text;
        const serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;
        const date = new Date(dateInput.value).toLocaleDateString();
        const time = selectedTimeInput.value;

        if (time) {
            summaryText.innerHTML = `
                <strong>Service:</strong> ${serviceName}<br>
                <strong>Stylist:</strong> ${stylistName}<br>
                <strong>Date:</strong> ${date}<br>
                <strong>Time:</strong> ${time}
            `;
            summaryDiv.classList.remove('d-none');
        }
    }

    stylistSelect.addEventListener('change', loadAvailableSlots);
    serviceSelect.addEventListener('change', loadAvailableSlots);
    dateInput.addEventListener('change', loadAvailableSlots);

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = {
            stylist_id: stylistSelect.value,
            service_id: serviceSelect.value,
            booking_date: dateInput.value,
            booking_time: selectedTimeInput.value,
            notes: document.getElementById('notes').value
        };

        fetch('/booking/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert(data.error || 'Booking failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    // Cancel booking handler
    document.querySelectorAll('.cancel-booking').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel this booking?')) {
                const bookingId = this.dataset.id;
                fetch(`/booking/cancel/${bookingId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.error);
                    }
                });
            }
        });
    });
});
</script>
{% endblock %}
```

## Database Migration

Run these commands to create the database tables:

```bash
docker exec project2-php-1 php bin/console make:migration
docker exec project2-php-1 php bin/console doctrine:migrations:migrate -n
```

## Seed Data Command

Create sample stylists and services:

```bash
docker exec project2-php-1 php bin/console app:seed-salon-data
```

## Testing the System

1. **Create Admin User:**
```bash
docker exec project2-php-1 php bin/console app:create-admin
```

2. **Access Pages:**
- User Booking: http://localhost/booking
- Admin Panel: http://localhost/admin/bookings

3. **Test Features:**
- Book an appointment as regular user
- Check email confirmation (async via Redis)
- View booking in admin panel
- Update booking status as admin
- Cancel booking as user

## Key Features Implemented

✅ Role-Based Access Control (ROLE_USER, ROLE_ADMIN)
✅ Database entities with proper relationships
✅ Double-booking prevention with transactions
✅ Real-time slot availability checking
✅ Async email confirmations via Redis
✅ Calendar interface for users and admins
✅ Admin dashboard with statistics
✅ Responsive frontend with Bootstrap

## Security Features

- CSRF protection on forms
- Role-based authorization
- Owner verification for cancellations
- Transaction-based booking to prevent race conditions
- Input validation and sanitization
