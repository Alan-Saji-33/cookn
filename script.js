// Toggle profile sections
document.addEventListener('DOMContentLoaded', function() {
    // Profile page tabs
    const profileLinks = document.querySelectorAll('.profile-menu a');
    if (profileLinks.length > 0) {
        profileLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href').substring(1);
                
                // Update active tab
                profileLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding section
                document.querySelectorAll('.profile-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(target).classList.add('active');
            });
        });
    }
    
    // Admin dashboard tabs
    const dashboardLinks = document.querySelectorAll('.dashboard-menu a');
    if (dashboardLinks.length > 0) {
        dashboardLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href').substring(1);
                
                // Update active tab
                dashboardLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding section
                document.querySelectorAll('.dashboard-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(target).classList.add('active');
            });
        });
    }
    
    // Car gallery thumbnail click
    const thumbnails = document.querySelectorAll('.car-thumbnail');
    if (thumbnails.length > 0) {
        const mainImage = document.querySelector('.car-main-image img');
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainImage.src = this.querySelector('img').src;
            });
        });
    }
    
    // ID preview for verification
    const idInput = document.getElementById('id_proof');
    if (idInput) {
        const idPreview = document.getElementById('id_preview');
        
        idInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    idPreview.src = e.target.result;
                    idPreview.style.display = 'block';
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Toggle favorite cars
function toggleFavorite(button, carId) {
    if (!button.classList.contains('active')) {
        // Add to favorites
        fetch('favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `car_id=${carId}&action=add`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.classList.add('active');
            }
        });
    } else {
        // Remove from favorites
        fetch('favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `car_id=${carId}&action=remove`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.classList.remove('active');
            }
        });
    }
}

// Show verification modal for unverified sellers
document.addEventListener('DOMContentLoaded', function() {
    const addCarBtn = document.getElementById('add-car-btn');
    if (addCarBtn) {
        addCarBtn.addEventListener('click', function(e) {
            const isVerified = this.getAttribute('data-verified') === 'true';
            if (!isVerified) {
                e.preventDefault();
                openModal('verification-modal');
            }
        });
    }
});

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}
