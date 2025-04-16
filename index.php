<?php
// MUST be first thing in file
session_start();

// Set page title
$page_title = "Medicare - Home";

// Include header
require_once 'header.php';
?>

<?php
// Include footer
require_once 'footer.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Hero Section -->
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

        <!-- Chatbot Icon -->
        <div class="chatbot-icon bg-primary rounded-circle shadow">
            <i class="fas fa-comment-dots text-white"></i>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services py-5 bg-light" id="services">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Medical Services</h2>
                <p class="lead text-muted">We provide a wide range of medical services to meet all your healthcare needs
                    with professional and experienced staff.</p>
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
                    echo '<div class="card service-card h-100 p-4">';
                    echo '<div class="service-icon text-center">';
                    echo '<i class="' . $service['icon'] . ' fa-3x mb-3"></i>';
                    echo '</div>';
                    echo '<div class="card-body text-center">';
                    echo '<h3 class="h4 card-title fw-bold">' . $service['title'] . '</h3>';
                    echo '<p class="card-text text-muted">' . $service['desc'] . '</p>';
                    echo '<a href="services.php?service=' . urlencode(strtolower($service['title'])) . '" class="btn btn-outline-primary mt-3">Read More</a>';
                    echo '</div></div></div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
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
                        $features = [
                            '24/7 Emergency Service',
                            'Qualified Doctors',
                            'Modern Equipment',
                            'Affordable Prices'
                        ];

                        foreach ($features as $feature) {
                            echo '<div class="col-md-6">';
                            echo '<div class="d-flex align-items-center">';
                            echo '<div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">';
                            echo '<i class="fas fa-check"></i>';
                            echo '</div>';
                            echo '<span>' . $feature . '</span>';
                            echo '</div></div>';
                        }
                        ?>
                    </div>

                    <a href="about.php" class="btn btn-primary btn-lg px-4">Learn More About Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors Section -->
    <section class="doctors py-5 bg-light" id="doctors">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Expert Doctors</h2>
                <p class="lead text-muted">Meet our team of experienced and qualified doctors who are dedicated to
                    providing you with the best healthcare services.</p>
            </div>

            <div class="row g-4">
                <?php
                $doctors = [
                    ['name' => 'Dr. Sarah chelimo', 'specialty' => 'Cardiologist', 'image' => 'download33.jpg'],
                    ['name' => 'Dr. Michael Chen', 'specialty' => 'Neurologist', 'image' => 'download23.jpg'],
                    ['name' => 'Dr. ivan wasswa', 'specialty' => 'Pediatrician', 'image' => 'download44.jpg'],
                    ['name' => 'Dr. Robert Williams', 'specialty' => 'Dentist', 'image' => 'download45.jpg']
                ];

                foreach ($doctors as $doctor) {
                    echo '<div class="col-md-6 col-lg-3">';
                    echo '<div class="card doctor-card h-100 overflow-hidden border-0 shadow-sm">';
                    echo '<div class="doctor-image overflow-hidden">';
                    echo '<img src="assets/images/doctors/' . $doctor['image'] . '" class="card-img-top" alt="' . $doctor['name'] . '">';
                    echo '</div>';
                    echo '<div class="card-body text-center">';
                    echo '<h3 class="h5 card-title fw-bold">' . $doctor['name'] . '</h3>';
                    echo '<p class="card-text text-muted">' . $doctor['specialty'] . '</p>';
                    echo '</div>';
                    echo '<div class="card-footer bg-white border-0 text-center pb-3">';
                    echo '<div class="d-flex justify-content-center gap-3">';
                    echo '<a href="#" class="text-primary"><i class="fab fa-facebook-f"></i></a>';
                    echo '<a href="#" class="text-primary"><i class="fab fa-twitter"></i></a>';
                    echo '<a href="#" class="text-primary"><i class="fab fa-linkedin-in"></i></a>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div></div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
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
                                ['name' => 'novie marie', 'role' => 'Patient', 'text' => 'The care I received at Medicare was exceptional. The doctors were knowledgeable and took time to explain everything to me.', 'image' => 'image3.jpeg'],
                                ['name' => 'Maria Garcia', 'role' => 'Patient', 'text' => 'I highly recommend Medicare Health Center. The staff is friendly and professional, and the facilities are top-notch.', 'image' => 'image4.jpeg'],
                                ['name' => 'shanie Wilson', 'role' => 'Patient', 'text' => 'From the moment I walked in, I felt cared for. The entire team made my recovery process smooth and comfortable.', 'image' => 'image5.jpeg']
                            ];

                            foreach ($testimonials as $index => $testimonial) {
                                $active = $index === 0 ? 'active' : '';
                                echo '<div class="carousel-item ' . $active . '">';
                                echo '<div class="card border-0 bg-light p-4 mx-auto" style="max-width: 800px;">';
                                echo '<div class="card-body text-center">';
                                echo '<img src="assets/images/patients/' . $testimonial['image'] . '" class="rounded-circle mb-3" width="80" height="80" alt="' . $testimonial['name'] . '">';
                                echo '<p class="lead mb-4">"' . $testimonial['text'] . '"</p>';
                                echo '<h5 class="fw-bold mb-1">' . $testimonial['name'] . '</h5>';
                                echo '<p class="text-muted">' . $testimonial['role'] . '</p>';
                                echo '</div></div></div>';
                            }
                            ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel"
                            data-bs-slide="prev">
                            <span class="carousel-control-prev-icon bg-primary rounded-circle"
                                aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel"
                            data-bs-slide="next">
                            <span class="carousel-control-next-icon bg-primary rounded-circle"
                                aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact py-5 bg-light" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="fw-bold mb-3">Contact Us</h2>
                    <p class="lead text-muted mb-4">Have questions or need to schedule an appointment? Reach out to us.
                    </p>

                    <div class="mb-4">
                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Address</h5>
                                <p class="mb-0">123 Medical Drive, kabale, HC 12345</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Phone</h5>
                                <p class="mb-0">+256 762 165 888</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Email</h5>
                                <p class="mb-0">info@medicare.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-3">Send Us a Message</h3>
                            <form action="contact.php" method="POST">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Your Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="4"
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

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h3 class="h4 fw-bold mb-3"><i class="fas fa-hospital me-2"></i>Medicare</h3>
                    <p>We value every human life placed in our hands and constantly work towards meeting the
                        expectations of our patients.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-2">
                    <h4 class="h5 fw-bold mb-3">Quick Links</h4>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Services</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Doctors</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Appointment</a></li>
                    </ul>
                </div>

                <div class="col-md-6 col-lg-3">
                    <h4 class="h5 fw-bold mb-3">Services</h4>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Primary Healthcare</a>
                        </li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Emergency Care</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Cardiology</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Dental Care</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Neurology</a></li>
                    </ul>
                </div>

                <div class="col-lg-3">
                    <h4 class="h5 fw-bold mb-3">Newsletter</h4>
                    <p>Subscribe to our newsletter for the latest updates.</p>
                    <form class="mt-4">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your Email">
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>

            <hr class="my-4 bg-secondary">

            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <?php
                    $current_year = date("Y"); // Get the current year
                    ?>
                    <p class="mb-0">&copy; <?php echo $current_year; ?> Medicare. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="#" class="text-white-50 text-decoration-none">Privacy
                                Policy</a></li>
                        <li class="list-inline-item"><a href="#" class="text-white-50 text-decoration-none">Terms of
                                Service</a></li>
                        <li class="list-inline-item"><a href="#" class="text-white-50 text-decoration-none">Contact
                                Us</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="js/script.js"></script>

    <script>
        // Simple chatbot toggle functionality
        document.querySelector('.chatbot-icon').addEventListener('click', function () {
            alert('Chatbot functionality would be implemented here. This is just a demo.');
        });
    </script>
</body>

</html>