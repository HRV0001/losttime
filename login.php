<?php
/**
 * LostTime - Authentication Portal (Login & Register)
 * Built with HTML5, CSS3, JavaScript, AngularJS & PHP
 */
require_once __DIR__ . '/config/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" ng-app="authApp">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login & Sign Up — LostTime</title>
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .auth-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.5rem;
      background: radial-gradient(circle at 50% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 60%), var(--bg-primary);
    }
    .auth-card {
      width: 100%;
      max-width: 440px;
      background: var(--bg-secondary);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
      overflow: hidden;
      animation: modalEnter 0.25s ease-out;
    }
    .auth-header {
      padding: 2rem 2rem 1.25rem;
      text-align: center;
      border-bottom: 1px solid var(--border-color);
    }
    .auth-brand-icon {
      width: 52px;
      height: 52px;
      margin: 0 auto 0.85rem;
      background: linear-gradient(135deg, #6366f1, #f43f5e);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #fff;
      box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
    }
    .auth-tabs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-bottom: 1px solid var(--border-color);
      background: rgba(0, 0, 0, 0.15);
    }
    .auth-tab-btn {
      padding: 0.9rem;
      background: transparent;
      border: none;
      color: var(--text-dim);
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: var(--transition);
      border-bottom: 2px solid transparent;
    }
    .auth-tab-btn.active {
      color: var(--text-main);
      border-bottom-color: var(--accent-primary);
      background: var(--bg-secondary);
    }
    .auth-body {
      padding: 2rem;
    }
    .demo-box {
      margin-top: 1.25rem;
      padding: 1rem;
      background: rgba(99, 102, 241, 0.08);
      border: 1px dashed rgba(99, 102, 241, 0.35);
      border-radius: var(--radius-sm);
      text-align: center;
    }
  </style>
</head>
<body class="auth-page" ng-controller="AuthCtrl">

  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-brand-icon">
        <i class="fa-solid fa-hourglass-half"></i>
      </div>
      <h2 style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em;">LostTime</h2>
      <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
        Audit your 24 hours. Reclaim your focus.
      </p>
    </div>

    <!-- Toggle Tabs: Login vs Register -->
    <div class="auth-tabs">
      <button class="auth-tab-btn" ng-class="{'active': tab === 'login'}" ng-click="setTab('login')">
        Sign In
      </button>
      <button class="auth-tab-btn" ng-class="{'active': tab === 'register'}" ng-click="setTab('register')">
        Create Account
      </button>
    </div>

    <div class="auth-body">

      <!-- Alert Notification -->
      <div class="alert-box" ng-class="'alert-' + alert.type" ng-if="alert.show">
        <span><i class="fa-solid" ng-class="alert.type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'"></i> {{ alert.message }}</span>
        <button style="background:none; border:none; color:inherit; cursor:pointer;" ng-click="alert.show = false">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- ================= LOGIN FORM ================= -->
      <form ng-if="tab === 'login'" ng-submit="handleLogin()">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" placeholder="you@example.com" ng-model="loginForm.email" required autofocus>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" class="form-input" placeholder="••••••••" ng-model="loginForm.password" required>
        </div>

        <button type="submit" class="nav-btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem; margin-top: 0.5rem;" ng-disabled="isSubmitting">
          <span ng-if="!isSubmitting"><i class="fa-solid fa-right-to-bracket"></i> Sign In</span>
          <span ng-if="isSubmitting"><i class="fa-solid fa-spinner fa-spin"></i> Signing In...</span>
        </button>

        <!-- 1-Click Demo Login Box -->
        <div class="demo-box">
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            Testing the app? Use pre-loaded demo account:
          </p>
          <button type="button" class="nav-btn" style="border-color: var(--accent-primary); width: 100%; justify-content: center;" ng-click="fillDemoAccount()">
            <i class="fa-solid fa-bolt" style="color: var(--accent-primary);"></i> 1-Click Demo Login
          </button>
        </div>
      </form>

      <!-- ================= REGISTER FORM ================= -->
      <form ng-if="tab === 'register'" ng-submit="handleRegister()">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-input" placeholder="e.g. Alex Johnson" ng-model="regForm.name" required>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" placeholder="you@example.com" ng-model="regForm.email" required>
        </div>

        <div class="form-group">
          <label class="form-label">Password (Min 6 chars)</label>
          <input type="password" class="form-input" placeholder="••••••••" ng-model="regForm.password" minlength="6" required>
        </div>

        <button type="submit" class="nav-btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem; margin-top: 0.5rem;" ng-disabled="isSubmitting">
          <span ng-if="!isSubmitting"><i class="fa-solid fa-user-plus"></i> Create Free Account</span>
          <span ng-if="isSubmitting"><i class="fa-solid fa-spinner fa-spin"></i> Creating Account...</span>
        </button>
      </form>

    </div>
  </div>

  <!-- AngularJS 1.8.2 CDN -->
  <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>
  <script>
    const app = angular.module('authApp', []);

    app.controller('AuthCtrl', ['$scope', '$http', '$timeout', function($scope, $http, $timeout) {
      $scope.tab = 'login';
      $scope.isSubmitting = false;

      $scope.loginForm = { email: '', password: '' };
      $scope.regForm = { name: '', email: '', password: '' };

      $scope.alert = { show: false, message: '', type: 'danger' };

      // Check if redirected after logout
      if (window.location.search.includes('logged_out=1')) {
        $scope.alert = { show: true, message: 'You have been logged out successfully.', type: 'success' };
      }

      $scope.showAlert = function(msg, type = 'danger') {
        $scope.alert = { show: true, message: msg, type: type };
      };

      $scope.setTab = function(t) {
        $scope.tab = t;
        $scope.alert.show = false;
      };

      $scope.fillDemoAccount = function() {
        $scope.loginForm.email = 'demo@losttime.com';
        $scope.loginForm.password = 'password123';
        $scope.handleLogin();
      };

      $scope.handleLogin = function() {
        $scope.isSubmitting = true;
        $http.post('api/auth.php?action=login', $scope.loginForm)
          .then(function(res) {
            if (res.data.success) {
              $scope.showAlert('Sign in successful! Redirecting...', 'success');
              $timeout(function() {
                window.location.href = 'index.php';
              }, 600);
            } else {
              $scope.showAlert(res.data.message || 'Invalid credentials.');
              $scope.isSubmitting = false;
            }
          })
          .catch(function(err) {
            $scope.showAlert(err.data?.message || 'Login failed. Please check your credentials.');
            $scope.isSubmitting = false;
          });
      };

      $scope.handleRegister = function() {
        $scope.isSubmitting = true;
        $http.post('api/auth.php?action=register', $scope.regForm)
          .then(function(res) {
            if (res.data.success) {
              $scope.showAlert('Account created! Entering dashboard...', 'success');
              $timeout(function() {
                window.location.href = 'index.php';
              }, 700);
            } else {
              $scope.showAlert(res.data.message || 'Registration failed.');
              $scope.isSubmitting = false;
            }
          })
          .catch(function(err) {
            $scope.showAlert(err.data?.message || 'Registration error. Email might already exist.');
            $scope.isSubmitting = false;
          });
      };
    }]);
  </script>
</body>
</html>

