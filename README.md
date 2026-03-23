# 🚜 AgriMove - Farmers Transport System

AgriMove is a full-stack, 3-tier web platform designed to streamline agricultural logistics in Kenya. It acts as a secure intermediary, connecting farmers who need to move produce with verified, independent transporters. 

## ✨ Key Features

* **Role-Based Portals:** Dedicated dashboards for Farmers, Transporters, and Administrators.
* **Mapbox Routing Engine:** Interactive map interface for farmers to drop pickup/delivery pins, automatically calculating route distances.
* **Live Load Board & Bidding:** Transporters can browse active farm requests and submit competitive monetary bids based on distance and cargo weight.
* **Strict KYC Verification:** Transporters are locked from the load board until an Administrator reviews and approves their uploaded National ID and Logbook documents.
* **Secure OTP Handshake:** A 4-digit One-Time Password (PIN) is generated upon job acceptance. The driver must physically acquire this PIN from the farmer at the destination to mark the job as 'Delivered'.

## 🛠️ Technology Stack

* **Front-End:** HTML5, Tailwind CSS (Modern, responsive UI), JavaScript (Mapbox GL JS)
* **Back-End:** PHP 8.x (Core business logic, session management, routing)
* **Database:** MySQL (Relational data storage)
* **Architecture:** Client-Server Model (Presentation, Application, and Data layers)

## ⚙️ Local Setup & Installation

To run this project locally on your machine, follow these steps:

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/Mwaura00000/farm_transport.git](https://github.com/Mwaura00000/farm_transport.git)