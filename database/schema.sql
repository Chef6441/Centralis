DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS contracts;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS partners;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS brokers;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS other_costs;
DROP TABLE IF EXISTS contract_offers;
DROP TABLE IF EXISTS reports;

CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    contact_name TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    address TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE brokers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE partners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    broker_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL UNIQUE,
    revenue_share_percentage REAL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (broker_id) REFERENCES brokers(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    broker_id INTEGER NOT NULL,
    partner_id INTEGER,
    company_id INTEGER NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (broker_id) REFERENCES brokers(id) ON DELETE CASCADE,
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    broker_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    contract_start_date TEXT,
    contract_end_date TEXT,
    contract_value REAL,
    status TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (broker_id) REFERENCES brokers(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
);

CREATE TABLE quotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    broker_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    quote_date TEXT,
    term_months INTEGER,
    total_cost REAL,
    status TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (broker_id) REFERENCES brokers(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    contract_id INTEGER,
    invoice_number TEXT NOT NULL,
    invoice_date TEXT,
    amount REAL,
    status TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    UNIQUE (invoice_number)
);

CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_identifier TEXT NOT NULL,
    report_date TEXT,
    customer_business_name TEXT NOT NULL,
    customer_contact_name TEXT,
    customer_abn TEXT,
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
