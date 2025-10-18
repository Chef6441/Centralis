INSERT INTO companies (
    name,
    contact_name,
    contact_email,
    contact_phone,
    address
) VALUES
    ('GoBrokerage', 'GoBrokerage Team', 'hello@gobrokerage.com', '+61 2 0000 0000', NULL),
    ('Energy Referral Partners', 'Jordan Matthews', 'jordan@energyreferralpartners.com.au', '+61 2 9999 1111', NULL),
    ('Momentum Energy', NULL, 'support@momentum.com.au', '+61 3 1234 5678', 'Melbourne, VIC'),
    ('Energy Australia', NULL, 'service@energyaustralia.com.au', '+61 3 9876 5432', 'Melbourne, VIC'),
    ('Supplier 3', NULL, 'sales@supplier3.com.au', '+61 2 2468 1357', 'Sydney, NSW'),
    ('Discover 202 Pty Ltd', 'Nelsy Zreik', 'nelsy@discover202.com.au', '+61 2 8888 0000', 'Unit 12, 28 Logistics Drive, Erskine Park, NSW 2759'),
    ('Direct Industries Pty Ltd', 'Jamie Lee', 'jamie.lee@directindustries.com.au', '+61 3 7777 5555', '45 Market Street, Melbourne, VIC 3000');

INSERT INTO brokers (company_id)
VALUES (
    (SELECT id FROM companies WHERE name = 'GoBrokerage')
);

INSERT INTO partners (
    broker_id,
    company_id,
    revenue_share_percentage
) VALUES (
    (SELECT id FROM brokers WHERE company_id = (SELECT id FROM companies WHERE name = 'GoBrokerage')),
    (SELECT id FROM companies WHERE name = 'Energy Referral Partners'),
    20.0
);

INSERT INTO suppliers (company_id)
VALUES
    ((SELECT id FROM companies WHERE name = 'Momentum Energy')),
    ((SELECT id FROM companies WHERE name = 'Energy Australia')),
    ((SELECT id FROM companies WHERE name = 'Supplier 3'));

INSERT INTO clients (
    broker_id,
    partner_id,
    company_id
) VALUES
    (
        (SELECT id FROM brokers WHERE company_id = (SELECT id FROM companies WHERE name = 'GoBrokerage')),
        (SELECT id FROM partners WHERE company_id = (SELECT id FROM companies WHERE name = 'Energy Referral Partners')),
        (SELECT id FROM companies WHERE name = 'Discover 202 Pty Ltd')
    ),
    (
        (SELECT id FROM brokers WHERE company_id = (SELECT id FROM companies WHERE name = 'GoBrokerage')),
        NULL,
        (SELECT id FROM companies WHERE name = 'Direct Industries Pty Ltd')
    );

INSERT INTO deal (
    client_id,
    deal_type,
    reference
) VALUES
    (
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Discover 202 Pty Ltd'),
        'SME',
        'DISC-2024'
    ),
    (
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Direct Industries Pty Ltd'),
        'C&I',
        'DIRECT-2024'
    );

INSERT INTO site (
    deal_id,
    name,
    address_line1,
    address_line2,
    city,
    state,
    postcode
) VALUES
    (
        (SELECT id FROM deal WHERE reference = 'DISC-2024'),
        'Discover Logistics Hub',
        'Unit 12, 28 Logistics Drive',
        'Building A',
        'Erskine Park',
        'NSW',
        '2759'
    ),
    (
        (SELECT id FROM deal WHERE reference = 'DIRECT-2024'),
        'Direct Industries HQ',
        '45 Market Street',
        'Level 10',
        'Melbourne',
        'VIC',
        '3000'
    );

INSERT INTO nmi (
    site_id,
    identifier
) VALUES
    (
        (SELECT id FROM site WHERE name = 'Discover Logistics Hub'),
        '41039619655'
    ),
    (
        (SELECT id FROM site WHERE name = 'Direct Industries HQ'),
        '62012345678'
    );

INSERT INTO contract (
    client_id,
    supplier_id,
    contract_start_date,
    contract_end_date,
    contract_value,
    commission_rate,
    commission_amount
) VALUES
    (
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Discover 202 Pty Ltd'),
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Momentum Energy'),
        '2023-10-01',
        '2024-09-30',
        7850,
        0.05,
        392.5
    ),
    (
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Direct Industries Pty Ltd'),
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Energy Australia'),
        '2024-07-01',
        '2025-06-30',
        8600,
        0.045,
        387
    );

INSERT INTO contract_commissions (
    contract_id,
    commission_label,
    commission_value
) VALUES
    (
        (SELECT id FROM contract WHERE contract_start_date = '2023-10-01' AND contract_end_date = '2024-09-30'),
        'Upfront',
        300
    ),
    (
        (SELECT id FROM contract WHERE contract_start_date = '2024-07-01' AND contract_end_date = '2025-06-30'),
        'Ongoing',
        120
    );

INSERT INTO offers (
    deal_id,
    supplier_id,
    broker_id,
    client_id,
    report_id,
    state,
    contract_start_date,
    contract_end_date,
    contract_value,
    term_months,
    peak_rate,
    shoulder_rate,
    off_peak_rate,
    total_cost,
    diff_dollar,
    diff_percentage
) VALUES
    (
        (SELECT id FROM deal WHERE reference = 'DISC-2024'),
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Momentum Energy'),
        (SELECT b.id FROM brokers b JOIN companies c ON c.id = b.company_id WHERE c.name = 'GoBrokerage'),
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Discover 202 Pty Ltd'),
        (SELECT id FROM reports WHERE report_identifier = '146'),
        'Accepted',
        '2024-10-01',
        '2026-09-30',
        7200,
        24,
        0.093,
        0.11071,
        0.09319,
        7200,
        -650,
        -8.28
    ),
    (
        (SELECT id FROM deal WHERE reference = 'DISC-2024'),
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Energy Australia'),
        (SELECT b.id FROM brokers b JOIN companies c ON c.id = b.company_id WHERE c.name = 'GoBrokerage'),
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Discover 202 Pty Ltd'),
        (SELECT id FROM reports WHERE report_identifier = '146'),
        'Awaiting acceptance',
        '2024-10-01',
        '2026-09-30',
        7850,
        24,
        0.095,
        0.112,
        0.094,
        7850,
        0,
        0
    ),
    (
        (SELECT id FROM deal WHERE reference = 'DIRECT-2024'),
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Supplier 3'),
        (SELECT b.id FROM brokers b JOIN companies c ON c.id = b.company_id WHERE c.name = 'GoBrokerage'),
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Direct Industries Pty Ltd'),
        NULL,
        'Created',
        '2024-11-01',
        '2025-10-31',
        6800,
        12,
        0.091,
        0.105,
        0.089,
        6800,
        NULL,
        NULL
    );

INSERT INTO quotes (
    supplier_id,
    broker_id,
    client_id,
    quote_date,
    term_months,
    total_cost,
    status
) VALUES
    (
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Momentum Energy'),
        (SELECT b.id FROM brokers b JOIN companies c ON c.id = b.company_id WHERE c.name = 'GoBrokerage'),
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Discover 202 Pty Ltd'),
        '2024-08-01',
        24,
        7200,
        'Accepted'
    ),
    (
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Energy Australia'),
        (SELECT b.id FROM brokers b JOIN companies c ON c.id = b.company_id WHERE c.name = 'GoBrokerage'),
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Discover 202 Pty Ltd'),
        '2024-08-01',
        24,
        7850,
        'Declined'
    ),
    (
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Supplier 3'),
        (SELECT b.id FROM brokers b JOIN companies c ON c.id = b.company_id WHERE c.name = 'GoBrokerage'),
        (SELECT cl.id FROM clients cl JOIN companies c ON c.id = cl.company_id WHERE c.name = 'Direct Industries Pty Ltd'),
        '2024-08-02',
        12,
        6800,
        'Under Review'
    );

INSERT INTO invoices (
    supplier_id,
    contract_id,
    invoice_number,
    invoice_date,
    amount,
    status
) VALUES
    (
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Momentum Energy'),
        (SELECT id FROM contract WHERE contract_start_date = '2023-10-01' AND contract_end_date = '2024-09-30'),
        'INV-2024-001',
        '2024-10-15',
        7200,
        'Paid'
    ),
    (
        (SELECT s.id FROM suppliers s JOIN companies c ON c.id = s.company_id WHERE c.name = 'Energy Australia'),
        (SELECT id FROM contract WHERE contract_start_date = '2024-07-01' AND contract_end_date = '2025-06-30'),
        'INV-2024-002',
        '2024-09-30',
        7850,
        'Pending'
    );

INSERT INTO reports (
    report_identifier,
    report_date,
    customer_business_name,
    customer_contact_name,
    customer_abn,
    broker_consultant,
    site_nmi,
    site_current_retailer,
    site_contract_end_date,
    site_address_line1,
    site_address_line2,
    site_peak_kwh,
    site_shoulder_kwh,
    site_off_peak_kwh,
    site_total_kwh,
    contract_current_retailer,
    contract_term_months,
    current_cost,
    new_cost,
    validity_period,
    payment_terms
) VALUES (
    '146',
    '2025-09-25',
    'Discover 202 Pty Ltd',
    'Nelsy Zreik',
    '50 123 456 789',
    'Alex Dechnicz',
    '41039619655',
    'Momentum Energy',
    '2026-09-30',
    'Unit 12, 28 Logistics Drive',
    'Erskine Park, 2759, NSW, Australia',
    20080,
    47832,
    75929,
    143841,
    'Energy Australia',
    24,
    7850,
    7200,
    '30 Days',
    'Net 14'
);

INSERT INTO other_costs (report_id, cost_label, cost_amount) VALUES
    (1, 'Network Costs', 1000.0),
    (1, 'Cost 2', 1000.0),
    (1, 'Cost 3', 1000.0);
