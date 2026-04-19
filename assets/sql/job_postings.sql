-- Create job_postings table for employer job listings
CREATE TABLE IF NOT EXISTS job_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    slots INT NOT NULL DEFAULT 1,
    employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Seasonal') NOT NULL,
    required_skills TEXT NOT NULL,
    pwd_friendly TINYINT(1) DEFAULT 0,
    accessibility TEXT,
    status ENUM('active', 'filled', 'expired') DEFAULT 'active',
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_job_postings_employer (employer_id);
CREATE INDEX IF NOT EXISTS idx_job_postings_status (status);
CREATE INDEX IF NOT EXISTS idx_job_postings_date (posted_date);
