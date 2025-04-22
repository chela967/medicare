<?php
// index.php - Corrected Structure

// MUST be first thing in file
session_start();

// Set page title - This is fine here
$page_title = "Medicare - Home";

// Include configuration or functions if needed BEFORE any HTML output
// require_once 'config.php'; // Uncomment if needed here
// require_once 'functions.php'; // Uncomment if needed here
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Medicare'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .chatbot-icon {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: transform 0.2s ease-in-out;
        }

        .chatbot-icon:hover {
            transform: scale(1.1);
        }

        #chatbotWindow {
            position: fixed;
            bottom: 90px;
            /* Above the icon */
            right: 20px;
            width: 350px;
            max-width: 90%;
            height: 450px;
            /* Adjust height as needed */
            z-index: 999;
            display: none;
            /* Initially hidden */
            flex-direction: column;
            /* Ensure header/body/footer stack */
            border: none;
            /* Remove default card border if needed */
            border-radius: 0.5rem;
            /* Match Bootstrap's card rounding */
        }

        #chatbotWindow .card-body {
            overflow-y: auto;
            /* Allow scrolling for messages */
            flex-grow: 1;
            /* Make message area take available space */
            padding: 1rem;
            background-color: #f8f9fa;
            /* Light background for chat area */
        }

        #chatbotWindow .card-header {
            font-weight: 600;
        }

        #chatbotWindow .card-footer {
            padding: 0.75rem 1rem;
        }

        /* Styling for chat messages */
        .user-message,
        .bot-message {
            margin-bottom: 10px;
            overflow: hidden;
            /* contain floats */
            display: flex;
            /* Use flexbox for alignment */
        }

        .user-message {
            justify-content: flex-end;
        }

        /* Align user messages right */
        .bot-message {
            justify-content: flex-start;
        }

        /* Align bot messages left */

        .user-message>div,
        .bot-message>div {
            display: inline-block;
            max-width: 80%;
            word-wrap: break-word;
            border-radius: 0.75rem;
            /* Rounded bubbles */
            padding: 0.5rem 0.8rem;
            line-height: 1.4;
        }

        .user-message>div {
            background-color: #0d6efd;
            /* Bootstrap primary blue */
            color: white;
            border-bottom-right-radius: 0.2rem;
            /* Flat corner */
        }

        .bot-message>div {
            background-color: #e9ecef;
            /* Bootstrap light gray */
            color: #212529;
            /* Dark text */
            border-bottom-left-radius: 0.2rem;
            /* Flat corner */
        }

        /* Typing indicator style */
        #typingIndicator .bg-light {
            color: #6c757d;
            /* Muted text color */
            font-style: italic;
        }
    </style>
</head>

<body>

    <?php
    // Include the main header/navbar content AFTER the opening <body> tag
// Ensure header.php contains the navbar and other top-level elements.
// It should NOT contain <html>, <head>, or opening <body> tags itself.
    require_once 'header.php';
    ?>

    <section class="hero py-5" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h1 class="display-4 fw-bold mb-3">We Take Care of Your Health and Medical Needs</h1>
                    <p class="lead mb-4">A commitment to providing quality healthcare to all individuals seeking our
                        medical services. Our team of doctors and staff is dedicated to your wellness.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="appointment.php" class="btn btn-primary btn-lg px-4">Book Appointment</a>
                        <a href="about.php" class="btn btn-outline-primary btn-lg px-4">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/medicare.webp" alt="Medical Healthcare" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>
    <section class="services py-5 bg-light" id="services">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Medical Services</h2>
                <p class="lead text-muted">We provide a wide range of medical services to meet all your healthcare needs
                    with professional and experienced staff.</p>
            </div>

            <div class="row g-4">
                <?php
                // Example services data (consider moving to database or config)
                $services = [
                    ['icon' => 'fas fa-stethoscope', 'title' => 'Primary Healthcare', 'desc' => 'Comprehensive care for patients of all ages, from routine check-ups to the treatment of acute and chronic illnesses.'],
                    ['icon' => 'fas fa-syringe', 'title' => 'Vaccination', 'desc' => 'Preventive healthcare through immunizations for both children and adults against various diseases.'],
                    ['icon' => 'fas fa-brain', 'title' => 'Neurology', 'desc' => 'Specialized care for disorders of the nervous system, including the brain, spinal cord, and peripheral nerves.'],
                    ['icon' => 'fas fa-heart', 'title' => 'Cardiology', 'desc' => 'Diagnosis and treatment of heart disorders through advanced cardiac care and monitoring.'],
                    ['icon' => 'fas fa-eye', 'title' => 'Ophthalmology', 'desc' => 'Comprehensive eye care services including vision tests, corrective surgery, and treatment for eye disorders.'],
                    ['icon' => 'fas fa-tooth', 'title' => 'Dental Care', 'desc' => 'Complete oral health services from routine cleanings to complex dental procedures.']
                ];

                foreach ($services as $service) {
                    echo '<div class="col-md-6 col-lg-4">';
                    echo '<div class="card service-card h-100 p-4 text-center shadow-sm border-0">'; // Added text-center, shadow, border-0
                    echo '<div class="service-icon text-primary">'; // Changed text-center to text-primary
                    echo '<i class="' . htmlspecialchars($service['icon']) . ' fa-3x mb-3"></i>';
                    echo '</div>';
                    echo '<div class="card-body">';
                    echo '<h3 class="h4 card-title fw-bold">' . htmlspecialchars($service['title']) . '</h3>';
                    echo '<p class="card-text text-muted">' . htmlspecialchars($service['desc']) . '</p>';
                    echo '<a href="services.php?service=' . urlencode(strtolower($service['title'])) . '" class="btn btn-outline-primary mt-3 stretched-link">Read More</a>'; // Added stretched-link
                    echo '</div></div></div>';
                }
                ?>
            </div>
        </div>
    </section>
    <section class="about py-5" id="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="assets/images/image1.png" alt="About Medicare" class="img-fluid rounded shadow">
                </div>
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-3">About Medicare Health Center</h2>
                    <p class="lead text-muted mb-4">Medicare Health Center has been providing exceptional healthcare
                        services for over 25 years. Our state-of-the-art facilities and compassionate staff ensure that
                        every patient receives personalized care tailored to their unique needs.</p>

                    <div class="row g-3 mb-4">
                        <?php
                        $features = ['24/7 Emergency Service', 'Qualified Doctors', 'Modern Equipment', 'Affordable Prices'];
                        foreach ($features as $feature) {
                            echo '<div class="col-md-6">';
                            echo '<div class="d-flex align-items-center">';
                            echo '<div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 flex-shrink-0" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">'; // Sized icon container
                            echo '<i class="fas fa-check"></i>';
                            echo '</div>';
                            echo '<span>' . htmlspecialchars($feature) . '</span>';
                            echo '</div></div>';
                        }
                        ?>
                    </div>

                    <a href="about.php" class="btn btn-primary btn-lg px-4">Learn More About Us</a>
                </div>
            </div>
        </div>
    </section>
    <section class="doctors py-5 bg-light" id="doctors">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Expert Doctors</h2>
                <p class="lead text-muted">Meet our team of experienced and qualified doctors dedicated to providing the
                    best healthcare.</p>
            </div>
            <div class="row g-4">
                <?php
                // Example doctors data (replace with dynamic data from DB if needed)
                $doctors = [
                    ['name' => 'Dr. Sarah chelimo', 'specialty' => 'Cardiologist', 'image' => 'download33.jpg'],
                    ['name' => 'Dr. Michael Chen', 'specialty' => 'Neurologist', 'image' => 'download23.jpg'],
                    ['name' => 'Dr. ivan wasswa', 'specialty' => 'Pediatrician', 'image' => 'download44.jpg'],
                    ['name' => 'Dr. Robert Williams', 'specialty' => 'Dentist', 'image' => 'download45.jpg']
                ];
                foreach ($doctors as $doctor) {
                    echo '<div class="col-md-6 col-lg-3 d-flex">'; // Added d-flex for equal height cards
                    echo '<div class="card doctor-card w-100 overflow-hidden border-0 shadow-sm text-center">'; // Added w-100 and text-center
                    echo '<div class="doctor-image-wrapper ratio ratio-1x1">'; // Use ratio for consistent image aspect
                    echo '<img src="assets/images/doctors/' . htmlspecialchars($doctor['image']) . '" class="doctor-image img-fluid" alt="' . htmlspecialchars($doctor['name']) . '" style="object-fit: cover;">'; // img-fluid, object-fit
                    echo '</div>';
                    echo '<div class="card-body">';
                    echo '<h3 class="h5 card-title fw-bold mb-1">' . htmlspecialchars($doctor['name']) . '</h3>';
                    echo '<p class="card-text text-primary mb-2">' . htmlspecialchars($doctor['specialty']) . '</p>'; // Specialty in primary color
                    echo '<div class="d-flex justify-content-center gap-3 mt-2">'; // Social links
                    echo '<a href="#" class="text-secondary text-decoration-none"><i class="fab fa-facebook-f"></i></a>';
                    echo '<a href="#" class="text-secondary text-decoration-none"><i class="fab fa-twitter"></i></a>';
                    echo '<a href="#" class="text-secondary text-decoration-none"><i class="fab fa-linkedin-in"></i></a>';
                    echo '</div>';
                    echo '</div>';
                    // Removed card-footer for cleaner look
                    echo '</div></div>';
                }
                ?>
            </div>
        </div>
    </section>
    <section class="testimonials py-5" id="testimonials">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Patient Testimonials</h2>
                <p class="lead text-muted">What our patients say about our services</p>
            </div>
            <div class="row">
                <div class="col-12">
                    <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php
                            $testimonials = [
                                ['name' => 'Novie Marie', 'role' => 'Patient', 'text' => 'The care I received at Medicare was exceptional. The doctors were knowledgeable and took time to explain everything to me.', 'image' => 'image3.jpeg'],
                                ['name' => 'Maria Garcia', 'role' => 'Patient', 'text' => 'I highly recommend Medicare Health Center. The staff is friendly and professional, and the facilities are top-notch.', 'image' => 'image4.jpeg'],
                                ['name' => 'Shanie Wilson', 'role' => 'Patient', 'text' => 'From the moment I walked in, I felt cared for. The entire team made my recovery process smooth and comfortable.', 'image' => 'image5.jpeg']
                            ];
                            foreach ($testimonials as $index => $testimonial) {
                                $active = $index === 0 ? 'active' : '';
                                echo '<div class="carousel-item ' . $active . '">';
                                echo '<div class="card border-0 bg-light p-4 mx-auto shadow-sm" style="max-width: 800px;">'; // Added shadow
                                echo '<div class="card-body text-center">';
                                echo '<img src="assets/images/patients/' . htmlspecialchars($testimonial['image']) . '" class="rounded-circle mb-3 shadow-sm" width="80" height="80" alt="' . htmlspecialchars($testimonial['name']) . '" style="object-fit: cover;">'; // Added shadow, object-fit
                                echo '<p class="lead mb-4 fst-italic">"' . htmlspecialchars($testimonial['text']) . '"</p>'; // Italic text
                                echo '<h5 class="fw-bold mb-1">' . htmlspecialchars($testimonial['name']) . '</h5>';
                                echo '<p class="text-muted small">' . htmlspecialchars($testimonial['role']) . '</p>'; // Small text
                                echo '</div></div></div>';
                            }
                            ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel"
                            data-bs-slide="prev">
                            <span class="carousel-control-prev-icon bg-dark rounded-circle p-3"
                                aria-hidden="true"></span> <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel"
                            data-bs-slide="next">
                            <span class="carousel-control-next-icon bg-dark rounded-circle p-3"
                                aria-hidden="true"></span> <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="contact py-5 bg-light" id="contact">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Get In Touch</h2>
                <p class="lead text-muted">Have questions or need to schedule an appointment? Reach out to us.</p>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h3 class="h4 fw-bold mb-4">Contact Information</h3>
                    <div class="mb-4">
                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 flex-shrink-0"
                                style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-map-marker-alt fa-fw"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 fs-6">Address</h5>
                                <p class="mb-0 text-muted">123 Medical Drive, Kabale, HC 12345</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 flex-shrink-0"
                                style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-phone-alt fa-fw"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 fs-6">Phone</h5>
                                <p class="mb-0 text-muted">+256 762 165 888</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 flex-shrink-0"
                                style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-envelope fa-fw"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 fs-6">Email</h5>
                                <p class="mb-0 text-muted">info@medicare.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <h3 class="h4 fw-bold mb-4">Send Us a Message</h3>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <form action="contact_handler.php" method="POST">
                                <div class="mb-3">
                                    <label for="contactName" class="form-label">Your Name</label>
                                    <input type="text" class="form-control" id="contactName" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="contactEmail" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="contactEmail" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="contactSubject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="contactSubject" name="subject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="contactMessage" class="form-label">Message</label>
                                    <textarea class="form-control" id="contactMessage" name="message" rows="4"
                                        required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="chatbot-icon bg-primary rounded-circle shadow">
        <i class="fas fa-comment-dots text-white fs-3"></i>
    </div>
    <div id="chatbotWindow" class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            Medicare Assistant
            <button type="button" class="btn-close btn-close-white" aria-label="Close" id="closeChatbot"></button>
        </div>
        <div class="card-body flex-grow-1" id="chatbotMessages">
            <div class="d-flex mb-2 bot-message">
                <div class="bg-light p-2 rounded text-dark small">
                    Hello! How can I help you today?
                </div>
            </div>
        </div>
        <div class="card-footer bg-light">
            <form id="chatbotForm">
                <div class="input-group">
                    <input type="text" id="chatbotInput" class="form-control" placeholder="Type your message..."
                        autocomplete="off" required>
                    <button class="btn btn-primary" type="submit" id="chatbotSendBtn">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
    // Include the footer content BEFORE the closing </body> tag
// Ensure footer.php contains the main <footer> element and potentially the closing </body>/</html> tags
    require_once 'footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    // Define the path relative to the document root
    $jsFilePath = $_SERVER['DOCUMENT_ROOT'] . '/medicare/js/script.js'; // Assumes medicare is the root folder in htdocs
    $jsSrcPath = '/medicare/js/script.js'; // Path for the src attribute
    
    // Check if the file exists in the expected location
    if (file_exists($jsFilePath)) {
        echo '<script src="' . $jsSrcPath . '"></script>';
    } else {
        // Log if file is missing - check your XAMPP PHP error log
        // error_log("Notice: Custom script file not found at: " . $jsFilePath);
        // Create the directory if it doesn't exist (optional, requires write permissions)
        // $jsDir = dirname($jsFilePath);
        // if (!is_dir($jsDir)) {
        //     mkdir($jsDir, 0755, true);
        // }
        // Create an empty file if it doesn't exist (optional, requires write permissions)
        // if (!file_exists($jsFilePath)) {
        //    file_put_contents($jsFilePath, '');
        //    echo '<script src="' . $jsSrcPath . '"></script>'; // Include it now
        // }
    }
    ?>


    <script>
        // Wrap all JS in DOMContentLoaded to ensure HTML is ready
        document.addEventListener('DOMContentLoaded', (event) => {
            const chatbotIcon = document.querySelector('.chatbot-icon');
            const chatbotWindow = document.getElementById('chatbotWindow');
            const closeChatbotBtn = document.getElementById('closeChatbot');
            const chatbotMessages = document.getElementById('chatbotMessages');
            const chatbotForm = document.getElementById('chatbotForm');
            const chatbotInput = document.getElementById('chatbotInput');
            const chatbotSendBtn = document.getElementById('chatbotSendBtn'); // Get send button

            // Check if all chatbot elements were found
            if (chatbotIcon && chatbotWindow && closeChatbotBtn && chatbotMessages && chatbotForm && chatbotInput && chatbotSendBtn) {

                // --- Toggle Chat Window ---
                chatbotIcon.addEventListener('click', () => {
                    const isHidden = chatbotWindow.style.display === 'none' || chatbotWindow.style.display === '';
                    chatbotWindow.style.display = isHidden ? 'flex' : 'none';
                    if (isHidden) {
                        scrollToBottom();
                        chatbotInput.focus(); // Focus input when opened
                    }
                });

                closeChatbotBtn.addEventListener('click', () => {
                    chatbotWindow.style.display = 'none';
                });

                // --- Handle Sending Messages ---
                chatbotForm.addEventListener('submit', handleSendMessage);
                // Optional: Allow sending with Enter key
                // chatbotInput.addEventListener('keypress', function (e) {
                //    if (e.key === 'Enter' && !e.shiftKey) { // Send on Enter, allow Shift+Enter for newline
                //        e.preventDefault();
                //        handleSendMessage(e);
                //    }
                // });


                async function handleSendMessage(submitEvent) {
                    submitEvent.preventDefault();
                    const userMessage = chatbotInput.value.trim();
                    if (!userMessage) return;

                    addMessage(userMessage, 'user');
                    chatbotInput.value = '';
                    chatbotInput.disabled = true; // Disable input
                    chatbotSendBtn.disabled = true; // Disable button
                    chatbotSendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'; // Loading spinner

                    const apiUrl = 'http://127.0.0.1:5000/chat'; // Flask API endpoint
                    let thinkingIndicatorAdded = false;
                    let typingTimeout = null;

                    try {
                        // Add thinking indicator slightly delayed
                        typingTimeout = setTimeout(() => {
                            if (chatbotInput.disabled) { // Only add if still waiting
                                addMessage("...", 'bot', true);
                                thinkingIndicatorAdded = true;
                            }
                        }, 400); // 400ms delay

                        const response = await fetch(apiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ message: userMessage })
                        });

                        clearTimeout(typingTimeout); // Clear timeout if response is fast
                        if (thinkingIndicatorAdded) removeTypingIndicator();

                        const contentType = response.headers.get("content-type");
                        if (!response.ok || !contentType || !contentType.includes("application/json")) {
                            let errorText = `API Error ${response.status}. Expected JSON.`;
                            try { const text = await response.text(); console.error("API Error Response:", text); errorText = `Server error ${response.status}. Check logs.`; } catch (e) { }
                            throw new Error(errorText);
                        }

                        const data = await response.json();
                        const botReply = data.reply || "Sorry, I couldn't process that response.";
                        addMessage(botReply, 'bot');

                    } catch (error) {
                        clearTimeout(typingTimeout);
                        if (thinkingIndicatorAdded) removeTypingIndicator();
                        console.error('Chatbot API Fetch Error:', error);
                        addMessage(`Error: ${error.message}`, 'bot');
                    } finally {
                        chatbotInput.disabled = false; // Re-enable input
                        chatbotSendBtn.disabled = false; // Re-enable button
                        chatbotSendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send'; // Restore button text
                        chatbotInput.focus();
                    }
                } // end handleSendMessage


                // --- Helper Function to Add Messages ---
                function addMessage(message, sender, isTyping = false) {
                    if (!chatbotMessages) return;
                    const messageWrapper = document.createElement('div');
                    messageWrapper.classList.add(sender === 'user' ? 'user-message' : 'bot-message');
                    if (isTyping) messageWrapper.id = 'typingIndicator';

                    const messageBubble = document.createElement('div');
                    messageBubble.classList.add(sender === 'user' ? 'bg-primary' : 'bg-light');
                    messageBubble.classList.add('p-2', 'rounded', 'text-dark', 'small');
                    if (sender === 'user') messageBubble.classList.add('text-white');

                    // Basic check for safety - consider a more robust sanitizer if needed
                    const tempDiv = document.createElement('div');
                    tempDiv.textContent = message;
                    messageBubble.innerHTML = tempDiv.innerHTML; // Use innerHTML AFTER setting textContent for basic escaping

                    messageWrapper.appendChild(messageBubble);
                    chatbotMessages.appendChild(messageWrapper);
                    scrollToBottom();
                }

                // --- Helper to remove typing indicator ---
                function removeTypingIndicator() {
                    const typingIndicator = document.getElementById('typingIndicator');
                    if (typingIndicator) typingIndicator.remove();
                }

                // --- Helper Function to Scroll to Bottom ---
                function scrollToBottom() {
                    if (chatbotMessages) chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                }

            } else {
                console.error("Chatbot UI elements not found! Check HTML IDs: chatbotIcon, chatbotWindow, closeChatbot, chatbotMessages, chatbotForm, chatbotInput, chatbotSendBtn");
            }

        }); // End DOMContentLoaded listener
    </script>

    <?php
    // Ensure footer.php OR this file includes closing </body> and </html> tags.
    require_once 'footer.php';
    // </body>
    // </html>
    ?>