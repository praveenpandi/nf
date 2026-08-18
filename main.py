import pandas as pd
import pymysql
import re
from tkinter import Tk
from tkinter.filedialog import askopenfilenames
import os

# -------------------------
# MySQL Connection
# -------------------------
conn = pymysql.connect(
    host="localhost",
    user="root",
    password="",      # XAMPP default
    database="test",
    charset="utf8mb4"
)

cursor = conn.cursor()

# -------------------------
# Select Excel Files
# -------------------------
Tk().withdraw()

excel_files = askopenfilenames(
    title="Select Excel Files",
    filetypes=[("Excel Files", "*.xlsx *.xls")]
)

if not excel_files:
    print("No files selected.")
    exit()


# -------------------------
# Clean Column Names
# -------------------------
def clean_column(col):
    col = str(col).strip().lower()
    col = col.replace("&", "and")
    col = re.sub(r"[^\w\s]", "", col)
    col = re.sub(r"\s+", "_", col)
    col = re.sub(r"_+", "_", col)
    col = col.strip("_")
    return col


# -------------------------
# Import Each Excel File
# -------------------------
for i, file in enumerate(excel_files, start=1):

    print(f"\nProcessing: {os.path.basename(file)}")

    df = pd.read_excel(file)

    # Clean headers
    df.columns = [clean_column(c) for c in df.columns]

    file_stem = os.path.splitext(os.path.basename(file))[0]
    table_name = clean_column(file_stem) or f"table{i}"

    # Drop existing table
    cursor.execute(f"DROP TABLE IF EXISTS `{table_name}`")

    # Create table
    columns_sql = []

    for col in df.columns:
        columns_sql.append(f"`{col}` TEXT")

    create_sql = f"""
    CREATE TABLE `{table_name}` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        {', '.join(columns_sql)}
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    """

    cursor.execute(create_sql)

    # Insert Data
    cols = ", ".join(f"`{c}`" for c in df.columns)
    placeholders = ", ".join(["%s"] * len(df.columns))

    insert_sql = f"""
    INSERT INTO `{table_name}` ({cols})
    VALUES ({placeholders})
    """

    rows = []

    for row in df.itertuples(index=False):
        cleaned_row = []
        for col_name, val in zip(df.columns, row):
            if pd.isna(val) or val is None or val == "":
                cleaned_row.append("")
            elif "date" in str(col_name).lower() and "update" not in str(col_name).lower():
                val_str = str(val).strip()
                formatted = val_str
                try:
                    if val_str.replace('.', '', 1).isdigit():
                        num = float(val_str)
                        if 10000 < num < 70000:
                            dt = pd.to_datetime('1899-12-30') + pd.to_timedelta(int(num), unit='D')
                            formatted = f"{dt.month}/{dt.day}/{dt.year}"
                    else:
                        dt = pd.to_datetime(val_str)
                        if not pd.isna(dt):
                            formatted = f"{dt.month}/{dt.day}/{dt.year}"
                except Exception:
                    pass
                cleaned_row.append(formatted)
            else:
                cleaned_row.append(str(val))
        rows.append(tuple(cleaned_row))

    cursor.executemany(insert_sql, rows)
    conn.commit()

    print(f"✓ Created table: {table_name}")
    print(f"✓ Inserted {len(rows)} rows")

cursor.close()
conn.close()

print("\n================================")
print("All Excel files imported successfully!")
print("================================")