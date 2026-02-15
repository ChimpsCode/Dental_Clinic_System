/**
 * User Avatar Handler - Ensures consistent avatar display across all account types
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeUserAvatars();
});

function initializeUserAvatars() {
    // Handle avatar image loading with fallback to initials
    const avatars = document.querySelectorAll('.user-avatar');
    
    avatars.forEach(avatar => {
        const img = avatar.querySelector('img');
        const initials = avatar.querySelector('.avatar-initials');
        
        if (img) {
            // If image fails to load, show initials
            img.addEventListener('error', function() {
                avatar.classList.remove('has-image');
                if (initials) {
                    initials.style.display = 'block';
                }
            });
            
            // If image loads successfully, hide initials
            img.addEventListener('load', function() {
                avatar.classList.add('has-image');
                if (initials) {
                    initials.style.display = 'none';
                }
            });
            
            // Check if image src is valid
            if (img.complete) {
                if (img.naturalHeight === 0) {
                    // Image failed to load
                    avatar.classList.remove('has-image');
                    if (initials) {
                        initials.style.display = 'block';
                    }
                } else {
                    // Image loaded successfully
                    avatar.classList.add('has-image');
                    if (initials) {
                        initials.style.display = 'none';
                    }
                }
            }
        }
    });
}

// Function to update user avatar (can be called from other scripts)
function updateUserAvatar(avatarId, name) {
    const avatar = document.getElementById(avatarId);
    if (avatar) {
        const firstLetter = name.charAt(0).toUpperCase();
        let initials = avatar.querySelector('.avatar-initials');
        
        if (!initials) {
            initials = document.createElement('span');
            initials.className = 'avatar-initials';
            avatar.appendChild(initials);
        }
        
        initials.textContent = firstLetter;
    }
}

// Generate avatar color based on name
function getAvatarColor(name) {
    const colors = [
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
        'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
        'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)'
    ];
    
    // Generate consistent color based on name
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    
    return colors[Math.abs(hash) % colors.length];
}