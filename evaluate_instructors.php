<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

// Get company information
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Create instructor_evaluations table if it doesn't exist
$create_evaluations_table = "CREATE TABLE IF NOT EXISTS instructor_evaluations (
    evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    instructor_id INT NOT NULL,
    instructor_request_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    evaluation_text TEXT DEFAULT NULL,
    teaching_quality INT NOT NULL CHECK (teaching_quality >= 1 AND teaching_quality <= 5),
    communication INT NOT NULL CHECK (communication >= 1 AND communication <= 5),
    punctuality INT NOT NULL CHECK (punctuality >= 1 AND punctuality <= 5),
    professionalism INT NOT NULL CHECK (professionalism >= 1 AND professionalism <= 5),
    would_recommend BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_request_id) REFERENCES instructor_requests(instructor_request_id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluation (company_id, instructor_id, instructor_request_id),
    INDEX idx_company_id (company_id),
    INDEX idx_instructor_id (instructor_id),
    INDEX idx_rating (rating)
)";

$conn->query($create_evaluations_table);

// Get instructor applications that can be evaluated
$evaluations_sql = "SELECT 
    ir.instructor_request_id,
    ir.course_title,
    ir.course_description,
    ir.created_at as course_created_at,
    ia.application_id,
    ia.instructor_id,
    ia.status as application_status,
    ia.applied_at,
    i.first_name,
    i.last_name,
    i.email as instructor_email,
    i.department as instructor_department,
    e.evaluation_id,
    e.rating as existing_rating,
    e.evaluation_text as existing_evaluation
    FROM instructor_requests ir
    JOIN instructor_applications ia ON ir.instructor_request_id = ia.instructor_request_id
    JOIN instructors i ON ia.instructor_id = i.instructor_id
    LEFT JOIN instructor_evaluations e ON (e.company_id = ir.company_id AND e.instructor_id = ia.instructor_id AND e.instructor_request_id = ir.instructor_request_id)
    WHERE ir.company_id = ? 
    AND ia.status = 'accepted'
    ORDER BY ir.created_at DESC";

$stmt = $conn->prepare($evaluations_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$instructor_evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle success/error messages from evaluation submission
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Evaluate Instructors – Company Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
  <style>
  /* ===== Global Reset ===== */
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: "Inter", sans-serif;
    background:
      radial-gradient(60rem 60rem at -10% -20%, rgba(14,165,168,0.06), transparent 60%),
      radial-gradient(50rem 50rem at 120% -10%, rgba(11,31,58,0.06), transparent 60%),
      #f6f8fb;
    color: #0f172a;
    overflow-x: hidden;
    line-height: 1.7;
    scroll-behavior: smooth;
  }

  :root { --brand:#0ea5a8; --brand-2:#22d3ee; --ink:#0b1f3a; --muted:#475569; --panel:#ffffff; --line:#e5e7eb; }
  ::selection { background: rgba(14,165,168,0.3); }

  /* Topbar */
  header {
    background: #ffffff;
    color: var(--ink);
    padding: 14px 5vw;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    z-index: 10;
    transition: box-shadow 0.2s ease;
  }
  header.scrolled { box-shadow: 0 8px 24px rgba(2,6,23,0.06); }

  header h1 {
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: 0.2px;
  }

  .user-info {
    display: flex;
    align-items: center;
    gap: 15px;
  }

  .user-name {
    font-weight: 600;
    color: var(--ink);
  }

  nav a {
    color: #334155;
    margin-left: 18px;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s ease;
    position: relative;
  }

  nav a:hover { color: var(--brand); }
  nav a::after { content:""; position:absolute; left:0; bottom:-6px; width:0; height:2px; background:var(--brand); transition:width 0.2s ease; }
  nav a:hover::after { width:100%; }
  nav a:focus { outline: none; }
  nav a:focus-visible { color:var(--brand); }

  .logout-btn {
    background: #ff4757;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease;
  }

  .logout-btn:hover {
    background: #ff3742;
  }

  /* Hero */
  .hero {
    text-align: left;
    padding: 90px 5vw;
    position: relative;
    color: #ffffff;
    background:var(--ink);
    overflow: hidden;
  }
  .hero::before { content:""; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=1600&auto=format&fit=crop') center/cover no-repeat; filter:brightness(0.55); }
  .hero::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg, rgba(11,31,58,0.85) 0%, rgba(14,165,168,0.35) 100%); background-size:200% 100%; animation:gradientShift 12s ease-in-out infinite alternate; }
  .hero-inner { position:relative; z-index:1; max-width: 1100px; }
  .hero h2 { font-size: clamp(2rem, 4vw, 3rem); color:#fff; margin-bottom: 12px; }
  .hero p { font-size: 1.05rem; color: #e2e8f0; max-width: 760px; }

  /* Message Styles */
  .message-container {
    margin: 20px 5vw;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    animation: slideDown 0.3s ease-out;
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .message-content {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    font-weight: 600;
    position: relative;
  }

  .success-message .message-content {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #10b981;
    color: #065f46;
  }

  .error-message .message-content {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #ef4444;
    color: #991b1b;
  }

  .message-icon {
    font-size: 1.2rem;
  }

  .message-text {
    flex: 1;
  }

  .message-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s ease;
  }

  .success-message .message-close {
    color: #065f46;
  }

  .error-message .message-close {
    color: #991b1b;
  }

  .message-close:hover {
    background: rgba(0, 0, 0, 0.1);
  }

  /* Main Content */
  .main-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 5vw;
  }

  .page-header {
    text-align: center;
    margin-bottom: 3rem;
  }

  .page-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--ink);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
  }

  .page-subtitle {
    font-size: 1.1rem;
    color: var(--muted);
    max-width: 600px;
    margin: 0 auto;
  }

  /* Evaluation Grid */
  .evaluation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
  }

  .evaluation-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .evaluation-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 4px;
    width: 0;
    background: linear-gradient(90deg, var(--brand), var(--brand-2));
    transition: width 0.35s ease;
  }

  .evaluation-card:hover {
    border-color: var(--brand);
    box-shadow: 0 20px 40px rgba(14, 165, 168, 0.15);
    transform: translateY(-8px);
  }

  .evaluation-card:hover::before {
    width: 100%;
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }

  .card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
  }

  .status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .status-badge.evaluated {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
  }

  .status-badge.pending {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
  }

  .course-info {
    background: rgba(14, 165, 168, 0.05);
    border: 1px solid rgba(14, 165, 168, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
  }

  .course-description {
    color: var(--muted);
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1rem;
  }

  .instructor-info {
    background: rgba(14, 165, 168, 0.05);
    border: 1px solid rgba(14, 165, 168, 0.1);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    font-size: 0.9rem;
  }

  .existing-evaluation {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
  }

  .rating-display {
    margin-bottom: 1rem;
  }

  .stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: 0.5rem;
  }

  .star {
    font-size: 1.3rem;
    color: #d1d5db;
    transition: color 0.2s ease;
  }

  .star.filled {
    color: #fbbf24;
  }

  .rating-number {
    margin-left: 0.5rem;
    font-weight: 600;
    color: var(--muted);
  }

  .evaluation-text {
    background: rgba(14, 165, 168, 0.05);
    border-left: 3px solid var(--brand);
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0 8px 8px 0;
    font-style: italic;
  }

  .btn {
    background: var(--brand);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn:hover {
    background: var(--brand-2);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(14, 165, 168, 0.3);
    color: white;
  }

  .btn-secondary {
    background: #6b7280;
    color: white;
  }

  .btn-secondary:hover {
    background: #4b5563;
  }

  .no-evaluations {
    text-align: center;
    padding: 4rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px dashed #cbd5e1;
    border-radius: 16px;
    margin: 2rem 0;
  }

  .no-evaluations .icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: var(--brand);
  }

  .no-evaluations h3 {
    color: var(--ink);
    margin-bottom: 1rem;
    font-size: 1.5rem;
  }

  .no-evaluations p {
    color: var(--muted);
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
  }

  /* Modal Styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
  }

  .modal-content {
    background-color: #ffffff;
    margin: 5% auto;
    padding: 0;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    animation: modalSlideIn 0.3s ease-out;
  }

  @keyframes modalSlideIn {
    from {
      opacity: 0;
      transform: translateY(-50px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    color: white;
    border-radius: 16px 16px 0 0;
  }

  .modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
  }

  .close {
    color: white;
    font-size: 2rem;
    font-weight: bold;
    cursor: pointer;
    transition: opacity 0.2s ease;
  }

  .close:hover {
    opacity: 0.7;
  }

  .modal form {
    padding: 2rem;
  }

  .form-group {
    margin-bottom: 1.5rem;
  }

  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--ink);
  }

  .rating-input {
    display: flex;
    gap: 0.5rem;
    align-items: center;
  }

  .rating-input input[type="radio"] {
    display: none;
  }

  .star-label {
    font-size: 2rem;
    color: #d1d5db;
    cursor: pointer;
    transition: color 0.2s ease;
  }

  .rating-input input[type="radio"]:checked ~ .star-label,
  .rating-input input[type="radio"]:checked ~ .star-label ~ .star-label {
    color: #fbbf24;
  }

  .rating-input .star-label:hover,
  .rating-input .star-label:hover ~ .star-label {
    color: #fbbf24;
  }

  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.2s ease;
  }

  .form-group select:focus,
  .form-group textarea:focus {
    outline: none;
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
  }

  .checkbox-group {
    display: flex;
    align-items: center;
  }

  .checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
  }

  .checkbox-label input[type="checkbox"] {
    display: none;
  }

  .checkmark {
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 4px;
    margin-right: 0.5rem;
    position: relative;
    transition: all 0.2s ease;
  }

  .checkbox-label input[type="checkbox"]:checked + .checkmark {
    background: var(--brand);
    border-color: var(--brand);
  }

  .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
    content: "✓";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
  }

  .modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
  }

  @keyframes gradientShift { 0% { background-position:0% 50%; } 100% { background-position:100% 50%; } }

  @media (max-width: 768px) {
    header { flex-direction: column; gap: 10px; }
    .hero h2 { font-size: 2rem; }
    .user-info { flex-direction: column; gap: 10px; }
    .evaluation-grid { grid-template-columns: 1fr; }
    .modal-content { width: 95%; margin: 10% auto; }
    .form-row { grid-template-columns: 1fr; }
    .modal-actions { flex-direction: column; }
  }
  </style>
</head>
<body>

  <header>
    <h1>Company Portal</h1>
    <div class="user-info">
      <nav>
        <a href="company_dashboard.php">Dashboard</a>
        <a href="company_profile.php">Company Profile</a>
        <a href="manage_locations.php">Manage Locations</a>
        <a href="manage_equipment.php">Manage Equipment</a>
        <a href="collaboration_request.php">Collaboration Requests</a>
      </nav>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <h2 class="user-name">Welcome, <?php echo htmlspecialchars($company['company_name']); ?>!</h2>
      <p>Evaluate instructors who have applied to your teaching opportunities and provide valuable feedback.</p>
    </div>
  </section>

  <!-- Success/Error Messages -->
  <?php if ($success_message): ?>
    <div class="message-container success-message">
      <div class="message-content">
        <span class="message-icon">✅</span>
        <span class="message-text"><?php echo htmlspecialchars($success_message); ?></span>
        <button class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error_message): ?>
    <div class="message-container error-message">
      <div class="message-content">
        <span class="message-icon">❌</span>
        <span class="message-text"><?php echo htmlspecialchars($error_message); ?></span>
        <button class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
      </div>
    </div>
  <?php endif; ?>

  <div class="main-content">
    <div class="page-header">
      <h1 class="page-title">
        <span>⭐</span>
        Evaluate Instructors
      </h1>
      <p class="page-subtitle">Review and rate instructors who have applied to your teaching opportunities</p>
    </div>
    
    <?php if (!empty($instructor_evaluations)): ?>
      <div class="evaluation-grid">
        <?php foreach ($instructor_evaluations as $evaluation): ?>
          <div class="evaluation-card">
            <div class="card-header">
              <h3 class="card-title"><?php echo htmlspecialchars($evaluation['course_title']); ?></h3>
              <span class="status-badge <?php echo $evaluation['existing_rating'] ? 'evaluated' : 'pending'; ?>">
                <?php echo $evaluation['existing_rating'] ? 'Evaluated' : 'Pending Evaluation'; ?>
              </span>
            </div>
            
            <div class="course-info">
              <p class="course-description"><?php echo htmlspecialchars($evaluation['course_description']); ?></p>
            </div>
            
            <div class="instructor-info">
              <strong>Instructor:</strong> <?php echo htmlspecialchars($evaluation['first_name'] . ' ' . $evaluation['last_name']); ?><br>
              <strong>Department:</strong> <?php echo htmlspecialchars($evaluation['instructor_department']); ?><br>
              <strong>Applied Date:</strong> <?php echo date('M d, Y', strtotime($evaluation['applied_at'])); ?>
            </div>
            
            <?php if ($evaluation['existing_rating']): ?>
              <div class="existing-evaluation">
                <div class="rating-display">
                  <strong>Your Rating:</strong>
                  <div class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <span class="star <?php echo $i <= $evaluation['existing_rating'] ? 'filled' : ''; ?>">★</span>
                    <?php endfor; ?>
                    <span class="rating-number">(<?php echo $evaluation['existing_rating']; ?>/5)</span>
                  </div>
                </div>
                <?php if ($evaluation['existing_evaluation']): ?>
                  <div class="evaluation-text">
                    <strong>Your Evaluation:</strong><br>
                    <em>"<?php echo htmlspecialchars($evaluation['existing_evaluation']); ?>"</em>
                  </div>
                <?php endif; ?>
                <button class="btn edit-evaluation-btn" 
                        data-course-id="<?php echo $evaluation['instructor_request_id']; ?>"
                        data-instructor-id="<?php echo $evaluation['instructor_id']; ?>"
                        data-existing-rating="<?php echo $evaluation['existing_rating']; ?>"
                        data-existing-evaluation="<?php echo htmlspecialchars($evaluation['existing_evaluation']); ?>">
                  <span>Edit Evaluation</span>
                  <span>✏️</span>
                </button>
              </div>
            <?php else: ?>
              <button class="btn evaluate-btn" 
                      data-course-id="<?php echo $evaluation['instructor_request_id']; ?>"
                      data-instructor-id="<?php echo $evaluation['instructor_id']; ?>"
                      data-course-title="<?php echo htmlspecialchars($evaluation['course_title']); ?>"
                      data-instructor-name="<?php echo htmlspecialchars($evaluation['first_name'] . ' ' . $evaluation['last_name']); ?>">
                <span>Evaluate Instructor</span>
                <span>⭐</span>
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="no-evaluations">
        <div class="icon">📚</div>
        <h3>No Instructor Applications Yet</h3>
        <p>You don't have any accepted instructor applications to evaluate at the moment. Once instructors apply and are accepted for your teaching opportunities, they will appear here for evaluation.</p>
        <a href="post_instructor_request.php" class="btn">
          <span>Post Teaching Request</span>
          <span>➕</span>
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Evaluation Modal -->
  <div id="evaluationModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Evaluate Instructor</h3>
        <span class="close">&times;</span>
      </div>
      <form id="evaluationForm" method="POST" action="submit_instructor_evaluation.php">
        <input type="hidden" id="courseId" name="course_id">
        <input type="hidden" id="instructorId" name="instructor_id">
        <input type="hidden" id="isEdit" name="is_edit" value="0">
        
        <div class="form-group">
          <label for="overallRating">Overall Rating *</label>
          <div class="rating-input">
            <input type="radio" id="rating5" name="rating" value="5">
            <label for="rating5" class="star-label">★</label>
            <input type="radio" id="rating4" name="rating" value="4">
            <label for="rating4" class="star-label">★</label>
            <input type="radio" id="rating3" name="rating" value="3">
            <label for="rating3" class="star-label">★</label>
            <input type="radio" id="rating2" name="rating" value="2">
            <label for="rating2" class="star-label">★</label>
            <input type="radio" id="rating1" name="rating" value="1">
            <label for="rating1" class="star-label">★</label>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="teachingQuality">Teaching Quality *</label>
            <select id="teachingQuality" name="teaching_quality" required>
              <option value="">Select Rating</option>
              <option value="1">1 - Poor</option>
              <option value="2">2 - Fair</option>
              <option value="3">3 - Good</option>
              <option value="4">4 - Very Good</option>
              <option value="5">5 - Excellent</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="communication">Communication *</label>
            <select id="communication" name="communication" required>
              <option value="">Select Rating</option>
              <option value="1">1 - Poor</option>
              <option value="2">2 - Fair</option>
              <option value="3">3 - Good</option>
              <option value="4">4 - Very Good</option>
              <option value="5">5 - Excellent</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="punctuality">Punctuality *</label>
            <select id="punctuality" name="punctuality" required>
              <option value="">Select Rating</option>
              <option value="1">1 - Poor</option>
              <option value="2">2 - Fair</option>
              <option value="3">3 - Good</option>
              <option value="4">4 - Very Good</option>
              <option value="5">5 - Excellent</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="professionalism">Professionalism *</label>
            <select id="professionalism" name="professionalism" required>
              <option value="">Select Rating</option>
              <option value="1">1 - Poor</option>
              <option value="2">2 - Fair</option>
              <option value="3">3 - Good</option>
              <option value="4">4 - Very Good</option>
              <option value="5">5 - Excellent</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="evaluationText">Evaluation Comments</label>
          <textarea id="evaluationText" name="evaluation_text" rows="4" placeholder="Share your evaluation of this instructor..."></textarea>
        </div>

        <div class="form-group checkbox-group">
          <label class="checkbox-label">
            <input type="checkbox" id="wouldRecommend" name="would_recommend" value="1" checked>
            <span class="checkmark"></span>
            Would recommend this instructor
          </label>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="cancelEvaluation">Cancel</button>
          <button type="submit" class="btn">Submit Evaluation</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Evaluation Modal Functionality
    const modal = document.getElementById('evaluationModal');
    const evaluationForm = document.getElementById('evaluationForm');
    const modalTitle = document.getElementById('modalTitle');
    const courseIdInput = document.getElementById('courseId');
    const instructorIdInput = document.getElementById('instructorId');
    const isEditInput = document.getElementById('isEdit');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.getElementById('cancelEvaluation');

    // Open modal for new evaluation
    document.addEventListener('click', function(e) {
      if (e.target.closest('.evaluate-btn')) {
        const btn = e.target.closest('.evaluate-btn');
        const courseId = btn.dataset.courseId;
        const instructorId = btn.dataset.instructorId;
        const courseTitle = btn.dataset.courseTitle;
        const instructorName = btn.dataset.instructorName;
        
        modalTitle.textContent = `Evaluate ${instructorName} - ${courseTitle}`;
        courseIdInput.value = courseId;
        instructorIdInput.value = instructorId;
        isEditInput.value = '0';
        
        // Reset form
        evaluationForm.reset();
        document.getElementById('wouldRecommend').checked = true;
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
      }
      
      // Open modal for editing existing evaluation
      if (e.target.closest('.edit-evaluation-btn')) {
        const btn = e.target.closest('.edit-evaluation-btn');
        const courseId = btn.dataset.courseId;
        const instructorId = btn.dataset.instructorId;
        const existingRating = btn.dataset.existingRating;
        const existingEvaluation = btn.dataset.existingEvaluation;
        
        modalTitle.textContent = `Edit Evaluation`;
        courseIdInput.value = courseId;
        instructorIdInput.value = instructorId;
        isEditInput.value = '1';
        
        // Populate form with existing data
        document.querySelector(`input[name="rating"][value="${existingRating}"]`).checked = true;
        document.getElementById('evaluationText').value = existingEvaluation;
        document.getElementById('wouldRecommend').checked = true;
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
      }
    });

    // Close modal
    function closeModal() {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeModal();
      }
    });

    // Star rating interaction
    document.addEventListener('change', function(e) {
      if (e.target.name === 'rating') {
        const rating = parseInt(e.target.value);
        const starLabels = document.querySelectorAll('.star-label');
        
        starLabels.forEach((label, index) => {
          if (index < rating) {
            label.style.color = '#fbbf24';
          } else {
            label.style.color = '#d1d5db';
          }
        });
      }
    });

    // Form validation
    evaluationForm.addEventListener('submit', function(e) {
      const rating = document.querySelector('input[name="rating"]:checked');
      const teachingQuality = document.getElementById('teachingQuality').value;
      const communication = document.getElementById('communication').value;
      const punctuality = document.getElementById('punctuality').value;
      const professionalism = document.getElementById('professionalism').value;
      
      if (!rating || !teachingQuality || !communication || !punctuality || !professionalism) {
        e.preventDefault();
        alert('Please fill in all required fields (Overall Rating, Teaching Quality, Communication, Punctuality, and Professionalism).');
        return false;
      }
    });

    // Topbar shadow on scroll
    const topbar = document.querySelector('header');
    const onScroll = ()=>{ if(window.scrollY>10) topbar.classList.add('scrolled'); else topbar.classList.remove('scrolled'); };
    document.addEventListener('scroll', onScroll); onScroll();
  </script>
</body>
</html>
