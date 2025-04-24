<?php
// index.php - Modern UI Redesign

session_start();
$page_title = "Medicare - Home";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Medicare'; ?></title>

    <!-- Modern Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap"
        rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Modern CSS Variables -->
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #e0e7ff;
            --secondary-color: #059669;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --text-color: #334155;
            --text-light: #64748b;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
            line-height: 1.6;
            background-color: #ffffff;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 24px;
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 24px;
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .section {
            padding: 80px 0;
        }

        .section-title {
            position: relative;
            margin-bottom: 50px;
            text-align: center;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
            height: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .service-card {
            border-top: 3px solid var(--primary-color);
            padding: 30px 20px;
            text-align: center;
        }

        .service-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            border-radius: 50%;
            color: var(--primary-color);
            font-size: 32px;
        }

        .doctor-card .card-body {
            text-align: center;
            padding: 20px;
        }

        .doctor-image-wrapper {
            overflow: hidden;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .doctor-image {
            transition: transform 0.5s ease;
        }

        .doctor-card:hover .doctor-image {
            transform: scale(1.05);
        }

        .testimonial-card {
            border-left: 3px solid var(--primary-color);
            padding: 30px;
            background-color: white;
        }

        .testimonial-card img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 3px solid var(--primary-light);
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: 1;
        }

        .hero {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .hero:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 40%;
            height: 100%;
            background: url('assets/images/medicare.webp') no-repeat center right/contain;
            opacity: 0.8;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .bg-light {
            background-color: var(--light-color) !important;
        }

        /* Modern Chatbot Styles */
        .chatbot-icon {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
            transition: all 0.3s ease;
        }

        .chatbot-icon:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.4);
        }

        #chatbotWindow {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 380px;
            max-width: 90%;
            height: 500px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            z-index: 999;
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        #chatbotWindow .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        #chatbotWindow .card-body {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f8fafc;
        }

        #chatbotWindow .card-footer {
            padding: 15px;
            background-color: white;
            border-top: 1px solid #e2e8f0;
        }

        .user-message,
        .bot-message {
            margin-bottom: 15px;
            display: flex;
        }

        .user-message {
            justify-content: flex-end;
        }

        .bot-message {
            justify-content: flex-start;
        }

        .user-message>div,
        .bot-message>div {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .user-message>div {
            background-color: var(--primary-color);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .bot-message>div {
            background-color: white;
            color: var(--text-color);
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .hero:before {
                width: 100%;
                height: 40%;
                top: auto;
                bottom: 0;
                opacity: 0.4;
            }

            .section {
                padding: 60px 0;
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 80px 0;
            }

            .hero:before {
                display: none;
            }

            #chatbotWindow {
                width: 90%;
                right: 5%;
            }
        }
    </style>
</head>

<body>
    <?php require_once 'header.php'; ?>

    <!-- Modern Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 hero-content">
                    <h1 class="display-4 fw-bold mb-4">Compassionate Healthcare For Everyone</h1>
                    <p class="lead mb-4">Our team of dedicated healthcare professionals provides personalized care using
                        the latest medical technologies to ensure your wellbeing.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="appointment.php" class="btn btn-primary btn-lg px-4">Book Appointment</a>
                        <a href="about.php" class="btn btn-outline-primary btn-lg px-4">Learn More</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="section bg-light" id="services">
        <div class="container">
            <div class="section-title">
                <h2>Our Medical Services</h2>
                <p class="text-muted">Comprehensive care for all your health needs</p>
            </div>

            <div class="row g-4">
                <?php
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
                    echo '<div class="service-card card h-100">';
                    echo '<div class="service-icon">';
                    echo '<i class="' . htmlspecialchars($service['icon']) . '"></i>';
                    echo '</div>';
                    echo '<div class="card-body">';
                    echo '<h3 class="h4 card-title fw-bold">' . htmlspecialchars($service['title']) . '</h3>';
                    echo '<p class="card-text text-muted">' . htmlspecialchars($service['desc']) . '</p>';
                    echo '<a href="services.php?service=' . urlencode(strtolower($service['title'])) . '" class="btn btn-outline-primary mt-3">Read More</a>';
                    echo '</div></div></div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="section" id="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="assets/images/image1.png" alt="About Medicare" class="img-fluid rounded shadow">
                </div>
                <div class="col-lg-6">
                    <h2 class="mb-4">About Medicare Health Center</h2>
                    <p class="lead text-muted mb-4">With over 25 years of experience, Medicare Health Center combines
                        cutting-edge technology with compassionate care to provide exceptional healthcare services to
                        our community.</p>

                    <div class="row g-3 mb-4">
                        <?php
                        $features = ['24/7 Emergency Service', 'Qualified Doctors', 'Modern Equipment', 'Affordable Prices'];
                        foreach ($features as $feature) {
                            echo '<div class="col-md-6">';
                            echo '<div class="d-flex align-items-center">';
                            echo '<div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;">';
                            echo '<i class="fas fa-check"></i>';
                            echo '</div>';
                            echo '<span class="fw-medium">' . htmlspecialchars($feature) . '</span>';
                            echo '</div></div>';
                        }
                        ?>
                    </div>

                    <a href="about.php" class="btn btn-primary px-4">Learn More About Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors Section -->
    <section class="section bg-light" id="doctors">
        <div class="container">
            <div class="section-title">
                <h2>Our Expert Doctors</h2>
                <p class="text-muted">Meet our team of board-certified specialists</p>
            </div>
            <div class="row g-4">
                <?php
                $doctors = [
                    ['name' => 'Dr. Sarah Chelimo', 'specialty' => 'Cardiologist', 'image' => 'download33.jpg'],
                    ['name' => 'Dr. Michael Chen', 'specialty' => 'Neurologist', 'image' => 'download23.jpg'],
                    ['name' => 'Dr. Ivan Wasswa', 'specialty' => 'Pediatrician', 'image' => 'download44.jpg'],
                    ['name' => 'Dr. Robert Williams', 'specialty' => 'Dentist', 'image' => 'download45.jpg']
                ];
                foreach ($doctors as $doctor) {
                    echo '<div class="col-md-6 col-lg-3">';
                    echo '<div class="doctor-card card">';
                    echo '<div class="doctor-image-wrapper">';
                    echo '<img src="assets/images/doctors/' . htmlspecialchars($doctor['image']) . '" class="doctor-image img-fluid" alt="' . htmlspecialchars($doctor['name']) . '">';
                    echo '</div>';
                    echo '<div class="card-body">';
                    echo '<h3 class="h5 card-title fw-bold mb-1">' . htmlspecialchars($doctor['name']) . '</h3>';
                    echo '<p class="card-text text-primary mb-3">' . htmlspecialchars($doctor['specialty']) . '</p>';
                    echo '<div class="d-flex justify-content-center gap-3">';
                    echo '<a href="#" class="text-secondary"><i class="fab fa-facebook-f"></i></a>';
                    echo '<a href="#" class="text-secondary"><i class="fab fa-twitter"></i></a>';
                    echo '<a href="#" class="text-secondary"><i class="fab fa-linkedin-in"></i></a>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div></div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>Patient Testimonials</h2>
                <p class="text-muted">What our patients say about our services</p>
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
                                echo '<div class="testimonial-card mx-auto" style="max-width: 800px;">';
                                echo '<div class="text-center mb-4">';
                                echo '<img src="assets/images/patients/' . htmlspecialchars($testimonial['image']) . '" class="rounded-circle shadow-sm" width="80" height="80" alt="' . htmlspecialchars($testimonial['name']) . '">';
                                echo '</div>';
                                echo '<p class="lead text-center mb-4 fst-italic">"' . htmlspecialchars($testimonial['text']) . '"</p>';
                                echo '<h5 class="text-center fw-bold mb-1">' . htmlspecialchars($testimonial['name']) . '</h5>';
                                echo '<p class="text-center text-muted small">' . htmlspecialchars($testimonial['role']) . '</p>';
                                echo '</div></div>';
                            }
                            ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel"
                            data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel"
                            data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="section bg-light" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Get In Touch</h2>
                <p class="text-muted">Have questions or need to schedule an appointment?</p>
            </div>
            <div class="row">
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-4">Contact Information</h3>
                            <div class="mb-4">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3"
                                        style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-map-marker-alt fa-fw"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1 fs-6">Address</h5>
                                        <p class="mb-0 text-muted">123 Medical Drive, Kabale, HC 12345</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3"
                                        style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-phone-alt fa-fw"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1 fs-6">Phone</h5>
                                        <p class="mb-0 text-muted">+256 762 165 888</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3"
                                        style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-envelope fa-fw"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1 fs-6">Email</h5>
                                        <p class="mb-0 text-muted">info@medicare.com</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h5 class="fw-bold mb-3">Opening Hours</h5>
                                <ul class="list-unstyled text-muted">
                                    <li class="mb-2"><span class="fw-medium">Mon-Fri:</span> 8:00 AM - 8:00 PM</li>
                                    <li class="mb-2"><span class="fw-medium">Saturday:</span> 9:00 AM - 5:00 PM</li>
                                    <li><span class="fw-medium">Sunday:</span> Emergency Only</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-4">Send Us a Message</h3>
                            <form action="contact_handler.php" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contactName" class="form-label">Your Name</label>
                                        <input type="text" class="form-control" id="contactName" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="contactEmail" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="contactEmail" name="email"
                                            required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="contactSubject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="contactSubject" name="subject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="contactMessage" class="form-label">Message</label>
                                    <textarea class="form-control" id="contactMessage" name="message" rows="5"
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

    <!-- Chatbot Elements -->
    <div class="chatbot-icon">
        <i class="fas fa-comment-dots"></i>
    </div>

    <div id="chatbotWindow" class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Medicare Assistant</span>
            <button type="button" class="btn-close" aria-label="Close" id="closeChatbot"></button>
        </div>
        <div class="card-body" id="chatbotMessages">
            <div class="bot-message">
                <div>
                    Hello! I'm your Medicare assistant. How can I help you today?
                </div>
            </div>
        </div>
        <div class="card-footer">
            <form id="chatbotForm">
                <div class="input-group">
                    <input type="text" id="chatbotInput" class="form-control" placeholder="Type your message..."
                        autocomplete="off" required>
                    <button class="btn btn-primary" type="submit" id="chatbotSendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Chatbot functionality remains exactly the same
        document.addEventListener('DOMContentLoaded', (event) => {
            const chatbotIcon = document.querySelector('.chatbot-icon');
            const chatbotWindow = document.getElementById('chatbotWindow');
            const closeChatbotBtn = document.getElementById('closeChatbot');
            const chatbotMessages = document.getElementById('chatbotMessages');
            const chatbotForm = document.getElementById('chatbotForm');
            const chatbotInput = document.getElementById('chatbotInput');
            const chatbotSendBtn = document.getElementById('chatbotSendBtn');

            if (chatbotIcon && chatbotWindow && closeChatbotBtn && chatbotMessages && chatbotForm && chatbotInput && chatbotSendBtn) {
                chatbotIcon.addEventListener('click', () => {
                    const isHidden = chatbotWindow.style.display === 'none' || chatbotWindow.style.display === '';
                    chatbotWindow.style.display = isHidden ? 'flex' : 'none';
                    if (isHidden) {
                        scrollToBottom();
                        chatbotInput.focus();
                    }
                });

                closeChatbotBtn.addEventListener('click', () => {
                    chatbotWindow.style.display = 'none';
                });

                chatbotForm.addEventListener('submit', handleSendMessage);

                async function handleSendMessage(submitEvent) {
                    submitEvent.preventDefault();
                    const userMessage = chatbotInput.value.trim();
                    if (!userMessage) return;

                    addMessage(userMessage, 'user');
                    chatbotInput.value = '';
                    chatbotInput.disabled = true;
                    chatbotSendBtn.disabled = true;
                    chatbotSendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    const apiUrl = 'http://127.0.0.1:5000/chat';
                    let thinkingIndicatorAdded = false;
                    let typingTimeout = null;

                    try {
                        typingTimeout = setTimeout(() => {
                            if (chatbotInput.disabled) {
                                addMessage("...", 'bot', true);
                                thinkingIndicatorAdded = true;
                            }
                        }, 400);

                        const response = await fetch(apiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ message: userMessage })
                        });

                        clearTimeout(typingTimeout);
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
                        chatbotInput.disabled = false;
                        chatbotSendBtn.disabled = false;
                        chatbotSendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                        chatbotInput.focus();
                    }
                }

                function addMessage(message, sender, isTyping = false) {
                    if (!chatbotMessages) return;
                    const messageWrapper = document.createElement('div');
                    messageWrapper.classList.add(sender === 'user' ? 'user-message' : 'bot-message');
                    if (isTyping) messageWrapper.id = 'typingIndicator';

                    const messageBubble = document.createElement('div');
                    messageBubble.classList.add(sender === 'user' ? 'bg-primary' : 'bg-white');
                    if (sender === 'user') messageBubble.classList.add('text-white');

                    const tempDiv = document.createElement('div');
                    tempDiv.textContent = message;
                    messageBubble.innerHTML = tempDiv.innerHTML;

                    messageWrapper.appendChild(messageBubble);
                    chatbotMessages.appendChild(messageWrapper);
                    scrollToBottom();
                }

                function removeTypingIndicator() {
                    const typingIndicator = document.getElementById('typingIndicator');
                    if (typingIndicator) typingIndicator.remove();
                }

                function scrollToBottom() {
                    if (chatbotMessages) chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                }
            }
        });
    </script>
</body>

</html>