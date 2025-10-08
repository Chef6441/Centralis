DROP TABLE IF EXISTS other_costs;
DROP TABLE IF EXISTS contract_offers;
DROP TABLE IF EXISTS reports;

CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_identifier TEXT NOT NULL,
    report_date TEXT,
    customer_business_name TEXT NOT NULL,
    customer_contact_name TEXT,
    broker_consultant TEXT,
    site_nmi TEXT,
    site_current_retailer TEXT,
    site_contract_end_date TEXT,
    site_address_line1 TEXT,
    site_address_line2 TEXT,
    site_peak_kwh REAL,
    site_shoulder_kwh REAL,
    site_off_peak_kwh REAL,
    site_total_kwh REAL,
    contract_current_retailer TEXT,
    contract_term_months INTEGER,
    current_cost REAL,
    new_cost REAL,
    validity_period TEXT,
    payment_terms TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE contract_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    supplier_name TEXT NOT NULL,
    term_months INTEGER NOT NULL,
    peak_rate REAL,
    shoulder_rate REAL,
    off_peak_rate REAL,
    total_cost REAL,
    diff_dollar REAL,
    diff_percentage REAL,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE other_costs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    cost_label TEXT NOT NULL,
    cost_amount REAL NOT NULL,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);
