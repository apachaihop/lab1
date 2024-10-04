CREATE DATABASE IF NOT EXISTS lab1;

USE lab1;

CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_admin BOOLEAN DEFAULT FALSE
);

CREATE TABLE Repositories (
    repo_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);
CREATE TABLE RepositoryComments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT,
    user_id INT,
    comment TEXT NOT NULL,
    stars INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repo_id) REFERENCES Repositories(repo_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

CREATE TABLE CommentLikes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES RepositoryComments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (comment_id, user_id)
);

CREATE TABLE Branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repo_id) REFERENCES Repositories(repo_id)
);

CREATE TABLE Commits (
    commit_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    user_id INT,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES Branches(branch_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

CREATE TABLE Issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repo_id) REFERENCES Repositories(repo_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

CREATE TABLE Comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT,
    user_id INT,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES Issues(issue_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

CREATE TABLE PullRequests (
    pr_id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT,
    user_id INT,
    branch_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open', 'closed', 'merged') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repo_id) REFERENCES Repositories(repo_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (branch_id) REFERENCES Branches(branch_id)
);

CREATE TABLE PullRequestReviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    pr_id INT,
    user_id INT,
    review TEXT NOT NULL,
    status ENUM('approved', 'changes_requested', 'commented') DEFAULT 'commented',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pr_id) REFERENCES PullRequests(pr_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

CREATE TABLE Reviews(
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    review TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
