CREATE TABLE company (
    company_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT,
    abn TEXT,
    nmi_mirn TEXT,
    site_address TEXT,
    suburb TEXT,
    postcode TEXT
);

CREATE TABLE invoice (
    invoice_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    period TEXT,
    nmi_number TEXT,
    location2 TEXT,
    invoice TEXT,
    ex_gst REAL,
    gst REAL,
    total REAL,
    FOREIGN KEY (company_id) REFERENCES company(company_id)
);
