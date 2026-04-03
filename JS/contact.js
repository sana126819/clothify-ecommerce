// contact.js
function validateForm() {
    // Get form values
    let name = document.forms["contactForm"]["name"].value.trim();
    let email = document.forms["contactForm"]["email"].value.trim();
    let subject = document.forms["contactForm"]["subject"].value.trim();
    let message = document.forms["contactForm"]["message"].value.trim();

    // Validation
    if (name === "") {
        alert("Name is required");
        return false;
    }

    if (email === "") {
        alert("Email is required");
        return false;
    }

    const emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailPattern.test(email)) {
        alert("Please enter a valid email address");
        return false;
    }

    if (message === "") {
        alert("Message is required");
        return false;
    }

    // Success message
    alert("Thank you for your message! This is a demo project - your message has been logged in the console.");
    
    // Log the form data to console (for demo purposes)
    console.log("Form Submission:");
    console.log("Name:", name);
    console.log("Email:", email);
    console.log("Subject:", subject);
    console.log("Message:", message);
    
    // Reset form
    document.getElementById("contactForm").reset();
    
    return false; // Prevent actual form submission
}

// Update your HTML form tag to:
// <form id="contactForm" onsubmit="return validateForm()">