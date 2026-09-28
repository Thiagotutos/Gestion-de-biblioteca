import urllib.request
import sys
import os

url = "https://docs.google.com/spreadsheets/d/1rJ1wtDedMrBy0Ctaeab96_JHDHquLUvH/export?format=csv"
try:
    response = urllib.request.urlopen(url)
    data = response.read().decode('utf-8')
    print("DOWNLOAD SUCCESSFUL")
    print(data[:1000]) # Print first 1000 chars
    with open("sheet_data.csv", "w", encoding="utf-8") as f:
        f.write(data)
except Exception as e:
    print(f"Error: {e}")
