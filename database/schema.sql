CREATE TABLE company (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT,
    abn TEXT,
    nmi_mirn TEXT,
    site_address TEXT,
    suburb TEXT,
    postcode TEXT
);

CREATE TABLE invoice (
    company_id INTEGER NOT NULL,
    period TEXT,
    nmi_number TEXT,
    location2 TEXT,
    invoice TEXT,
    ex_gst REAL,
    gst REAL,
    total REAL,
    FOREIGN KEY (company_id) REFERENCES company(id)
);
