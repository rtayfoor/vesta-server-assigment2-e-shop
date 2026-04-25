<?php
session_start();
require_once 'conn.php';

if (isset($_SESSION['user_id'])) {
    header("Location: products.php");
    exit();
}

$error = '';
$success = '';
$form_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $form_data = [
        'user_name' => trim($_POST['user_name'] ?? ''),
        'user_email' => trim($_POST['user_email'] ?? ''),
        'user_address' => trim($_POST['user_address'] ?? '')
    ];
    $password = $_POST['user_pass'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($form_data['user_name'])) {
        $errors[] = "Full name is required.";
    } elseif (strlen($form_data['user_name']) < 3) {
        $errors[] = "Full name must be at least 3 characters long.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $form_data['user_name'])) {
        $errors[] = "Full name can only contain letters and spaces.";
    }
    
    if (empty($form_data['user_email'])) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($form_data['user_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $form_data['user_email'])) {
        $errors[] = "Email format is invalid.";
    } else {
        $check_sql = "SELECT user_id FROM tbl_users WHERE user_email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $form_data['user_email']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "This email address is already registered. Please use a different email or login.";
        }
        $check_stmt->close();
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one number.";
    } elseif (!preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]/", $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&* etc.).";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($form_data['user_address'])) {
        $errors[] = "Address is required.";
    } elseif (strlen($form_data['user_address']) < 10) {
        $errors[] = "Please enter your full address (minimum 10 characters).";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO tbl_users (user_name, user_email, user_pass, user_address) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $form_data['user_name'], $form_data['user_email'], $hashed_password, $form_data['user_address']);
        
        if ($stmt->execute()) {
            $success = "Registration successful! You can now login.";
            $form_data = [];
        } else {
            $errors[] = "Registration failed. Please try again later.";
        }
        $stmt->close();
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="style.css">
    <title>Register - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="register-container">
        <h1>Create an Account</h1>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
                <p style="margin-top: 10px;">
                    <a href="login.php" style="color: #155724; font-weight: bold;">Click here to login →</a>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message-box"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm" novalidate>
            <div class="form-group">
                <label for="user_name">Full Name <span class="required">*</span></label>
                <input type="text" id="user_name" name="user_name" required
                       value="<?php echo htmlspecialchars($form_data['user_name'] ?? ''); ?>"
                       pattern="[A-Za-z\s]{3,}"
                       title="Name must be at least 3 characters and contain only letters and spaces"
                       autocomplete="name">
                <div class="error-message" id="name-error"></div>
            </div>
            
            <div class="form-group">
                <label for="user_email">Email Address <span class="required">*</span></label>
                <input type="email" id="user_email" name="user_email" required
                       value="<?php echo htmlspecialchars($form_data['user_email'] ?? ''); ?>"
                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                       title="Please enter a valid email address"
                       autocomplete="email">
                <div class="error-message" id="email-error"></div>
            </div>
            
            <div class="form-group">
                <label for="user_pass">Password <span class="required">*</span></label>
                <input type="password" id="user_pass" name="user_pass" required autocomplete="new-password">
                <div class="strength-meter">
                    <div class="strength-meter-fill" id="strengthMeter"></div>
                </div>
                <div class="password-requirements">
                    <p>Password must contain:</p>
                    <p id="length-req">✓ At least 8 characters</p>
                    <p id="upper-req">✓ At least one uppercase letter</p>
                    <p id="lower-req">✓ At least one lowercase letter</p>
                    <p id="number-req">✓ At least one number</p>
                    <p id="special-req">✓ At least one special character (!@#$%^&*)</p>
                </div>
                <div class="error-message" id="password-error"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="off">
                <div class="error-message" id="confirm-error"></div>
            </div>
            
            <div class="form-group">
                <label for="user_address">Address <span class="required">*</span></label>
                <textarea id="user_address" name="user_address" rows="3" required
                          title="Please enter your full address"
                          autocomplete="address-line1"><?php echo htmlspecialchars($form_data['user_address'] ?? ''); ?></textarea>
                <div class="error-message" id="address-error"></div>
            </div>
            
            <button type="submit" name="register" class="register-btn">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('registerForm');
        const nameInput = document.getElementById('user_name');
        const emailInput = document.getElementById('user_email');
        const passwordInput = document.getElementById('user_pass');
        const confirmInput = document.getElementById('confirm_password');
        const addressInput = document.getElementById('user_address');
        
        function validateName() {
            const name = nameInput.value.trim();
            const nameError = document.getElementById('name-error');
            const namePattern = /^[A-Za-z\s]{3,}$/;
            
            if (name === '') {
                nameError.textContent = 'Full name is required.';
                nameError.classList.add('show');
                nameInput.classList.add('error');
                return false;
            } else if (!namePattern.test(name)) {
                nameError.textContent = 'Name must be at least 3 characters and contain only letters and spaces.';
                nameError.classList.add('show');
                nameInput.classList.add('error');
                return false;
            } else {
                nameError.textContent = '';
                nameError.classList.remove('show');
                nameInput.classList.remove('error');
                return true;
            }
        }
        
        function validateEmail() {
            const email = emailInput.value.trim();
            const emailError = document.getElementById('email-error');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (email === '') {
                emailError.textContent = 'Email address is required.';
                emailError.classList.add('show');
                emailInput.classList.add('error');
                return false;
            } else if (!emailPattern.test(email)) {
                emailError.textContent = 'Please enter a valid email address.';
                emailError.classList.add('show');
                emailInput.classList.add('error');
                return false;
            } else {
                emailError.textContent = '';
                emailError.classList.remove('show');
                emailInput.classList.remove('error');
                return true;
            }
        }
        
        function checkPasswordStrength() {
            const password = passwordInput.value;
            const meter = document.getElementById('strengthMeter');
            
            meter.className = 'strength-meter-fill';
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/)) strength++;
            
            if (strength <= 2) {
                meter.classList.add('strength-weak');
            } else if (strength === 3) {
                meter.classList.add('strength-fair');
            } else if (strength === 4) {
                meter.classList.add('strength-good');
            } else {
                meter.classList.add('strength-strong');
            }
        }
        
        function validatePassword() {
            const password = passwordInput.value;
            const passwordError = document.getElementById('password-error');
            
            document.getElementById('length-req').style.color = password.length >= 8 ? '#28a745' : '#dc3545';
            document.getElementById('upper-req').style.color = /[A-Z]/.test(password) ? '#28a745' : '#dc3545';
            document.getElementById('lower-req').style.color = /[a-z]/.test(password) ? '#28a745' : '#dc3545';
            document.getElementById('number-req').style.color = /[0-9]/.test(password) ? '#28a745' : '#dc3545';
            document.getElementById('special-req').style.color = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password) ? '#28a745' : '#dc3545';
            
            checkPasswordStrength();
            
            if (password === '') {
                passwordError.textContent = 'Password is required.';
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                return false;
            } else if (password.length < 8) {
                passwordError.textContent = 'Password must be at least 8 characters long.';
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                return false;
            } else if (!/[A-Z]/.test(password)) {
                passwordError.textContent = 'Password must contain at least one uppercase letter.';
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                return false;
            } else if (!/[a-z]/.test(password)) {
                passwordError.textContent = 'Password must contain at least one lowercase letter.';
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                return false;
            } else if (!/[0-9]/.test(password)) {
                passwordError.textContent = 'Password must contain at least one number.';
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                return false;
            } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                passwordError.textContent = 'Password must contain at least one special character.';
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                return false;
            } else {
                passwordError.textContent = '';
                passwordError.classList.remove('show');
                passwordInput.classList.remove('error');
                return true;
            }
        }
        
        function validateConfirmPassword() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            const confirmError = document.getElementById('confirm-error');
            
            if (confirm === '') {
                confirmError.textContent = 'Please confirm your password.';
                confirmError.classList.add('show');
                confirmInput.classList.add('error');
                return false;
            } else if (password !== confirm) {
                confirmError.textContent = 'Passwords do not match.';
                confirmError.classList.add('show');
                confirmInput.classList.add('error');
                return false;
            } else {
                confirmError.textContent = '';
                confirmError.classList.remove('show');
                confirmInput.classList.remove('error');
                return true;
            }
        }
        
        function validateAddress() {
            const address = addressInput.value.trim();
            const addressError = document.getElementById('address-error');
            
            if (address === '') {
                addressError.textContent = 'Address is required.';
                addressError.classList.add('show');
                addressInput.classList.add('error');
                return false;
            } else if (address.length < 10) {
                addressError.textContent = 'Please enter your full address (minimum 10 characters).';
                addressError.classList.add('show');
                addressInput.classList.add('error');
                return false;
            } else {
                addressError.textContent = '';
                addressError.classList.remove('show');
                addressInput.classList.remove('error');
                return true;
            }
        }
        
        nameInput.addEventListener('input', validateName);
        emailInput.addEventListener('input', validateEmail);
        passwordInput.addEventListener('input', function() {
            validatePassword();
            validateConfirmPassword();
        });
        confirmInput.addEventListener('input', validateConfirmPassword);
        addressInput.addEventListener('input', validateAddress);
        
        form.addEventListener('submit', function(e) {
            const isValidName = validateName();
            const isValidEmail = validateEmail();
            const isValidPassword = validatePassword();
            const isValidConfirm = validateConfirmPassword();
            const isValidAddress = validateAddress();
            
            if (!isValidName || !isValidEmail || !isValidPassword || !isValidConfirm || !isValidAddress) {
                e.preventDefault();
                alert('Please fix the errors in the form before submitting.');
            }
        });
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>