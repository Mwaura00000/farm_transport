<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriMove | Smart Farm Transport in Kenya</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Montserrat', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); }
        .blob-shape { animation: blob 7s infinite; }
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
    </style>
</head>
<body class="font-sans text-gray-800 antialiased overflow-x-hidden selection:bg-brand-500 selection:text-white">

    <nav class="glass-nav fixed w-full z-50 border-b border-gray-100 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center gap-2 cursor-pointer" onclick="window.scrollTo(0,0)">
                    <div class="w-10 h-10 bg-brand-600 rounded-lg flex items-center justify-center text-white text-xl shadow-lg shadow-brand-500/30">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <span class="font-heading font-bold text-2xl text-gray-900 tracking-tight">AgriMove</span>
                </div>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="#how-it-works" class="text-gray-600 hover:text-brand-600 font-medium transition">How it Works</a>
                    <a href="#benefits" class="text-gray-600 hover:text-brand-600 font-medium transition">Benefits</a>
                    <a href="#testimonials" class="text-gray-600 hover:text-brand-600 font-medium transition">Stories</a>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <a href="login.php" class="text-gray-900 hover:text-brand-600 font-semibold transition">Login</a>
                    <a href="register.php" class="bg-brand-600 hover:bg-brand-700 text-white px-6 py-2.5 rounded-full font-semibold transition shadow-md shadow-brand-500/20 transform hover:-translate-y-0.5">
                        Get Started Free
                    </a>
                </div>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-gray-600 hover:text-gray-900 focus:outline-none p-2">
                        <i class="fa-solid fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 absolute w-full shadow-xl">
            <div class="px-4 pt-2 pb-6 space-y-2">
                <a href="#how-it-works" class="block px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-brand-600 hover:bg-gray-50">How it Works</a>
                <a href="#benefits" class="block px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-brand-600 hover:bg-gray-50">Benefits</a>
                <a href="login.php" class="block px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-brand-600 hover:bg-gray-50">Login</a>
                <a href="register.php" class="block mt-4 w-full text-center bg-brand-600 text-white px-4 py-3 rounded-lg font-bold">Get Started Free</a>
            </div>
        </div>
    </nav>

    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="absolute top-0 -left-4 w-72 h-72 bg-brand-200 rounded-full mix-blend-multiply filter blur-2xl opacity-70 blob-shape"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-yellow-200 rounded-full mix-blend-multiply filter blur-2xl opacity-70 blob-shape" style="animation-delay: 2s;"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-blue-200 rounded-full mix-blend-multiply filter blur-2xl opacity-70 blob-shape" style="animation-delay: 4s;"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-brand-50 border border-brand-100 text-brand-700 font-medium text-sm mb-6">
                        <span class="flex h-2 w-2 rounded-full bg-brand-500"></span>
                        Connecting Kenya's Agricultural Supply Chain
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-heading font-extrabold text-gray-900 leading-tight mb-6">
                        Move Your Harvest <br/>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-600 to-green-400">Faster & Safer.</span>
                    </h1>
                    <p class="text-lg sm:text-xl text-gray-600 mb-8 max-w-2xl mx-auto lg:mx-0">
                        AgriMove directly connects farmers with trusted, verified transporters. Stop waiting for middle-men. Get your produce to market fresh, while saving on transport costs.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="register.php?role=farmer" class="bg-brand-600 hover:bg-brand-700 text-white px-8 py-4 rounded-xl font-bold text-lg transition shadow-lg shadow-brand-500/30 flex items-center justify-center gap-2">
                            I am a Farmer <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="register.php?role=transporter" class="bg-white hover:bg-gray-50 border-2 border-gray-200 text-gray-800 px-8 py-4 rounded-xl font-bold text-lg transition flex items-center justify-center gap-2">
                            I am a Transporter <i class="fa-solid fa-truck"></i>
                        </a>
                    </div>
                    <p class="mt-4 text-sm text-gray-500"><i class="fa-solid fa-shield-halved text-brand-500 mr-1"></i> 100% Free to join. Secure & Verified.</p>
                </div>

                <div class="relative hidden lg:block">
                    <img src="https://images.pexels.com/photos/1112080/pexels-photo-1112080.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Farm transport truck" class="rounded-2xl shadow-2xl z-10 relative object-cover h-[500px] w-full">
                    <div class="absolute -bottom-6 -left-6 bg-white p-6 rounded-xl shadow-xl z-20 border border-gray-100 flex items-center gap-4 animate-bounce" style="animation-duration: 3s;">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-600 text-xl">
                            <i class="fa-solid fa-check-double"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Successful Deliveries</p>
                            <p class="text-2xl font-bold text-gray-900">5,000+</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-brand-900 py-12 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-x divide-brand-800/50">
                <div>
                    <h3 class="text-4xl font-heading font-bold text-white mb-1">47</h3>
                    <p class="text-brand-200 font-medium">Counties Covered</p>
                </div>
                <div>
                    <h3 class="text-4xl font-heading font-bold text-white mb-1">12k+</h3>
                    <p class="text-brand-200 font-medium">Active Farmers</p>
                </div>
                <div>
                    <h3 class="text-4xl font-heading font-bold text-white mb-1">850+</h3>
                    <p class="text-brand-200 font-medium">Verified Trucks</p>
                </div>
                <div>
                    <h3 class="text-4xl font-heading font-bold text-white mb-1">99%</h3>
                    <p class="text-brand-200 font-medium">Delivery Success</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-brand-600 font-semibold tracking-wide uppercase text-sm mb-2">The Challenge</h2>
                <h3 class="text-3xl md:text-4xl font-heading font-bold text-gray-900 mb-4">Why we built AgriMove</h3>
                <p class="text-gray-600 text-lg">Traditional farm transport is broken. We are here to fix the supply chain so everyone wins.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-red-50 text-red-500 rounded-xl flex items-center justify-center text-2xl mb-6">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Post-Harvest Losses</h4>
                    <p class="text-gray-600 leading-relaxed">Nearly 30% of harvested produce rots at the farm while waiting days for unreliable transport to arrive.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center text-2xl mb-6">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Middlemen Exploitation</h4>
                    <p class="text-gray-600 leading-relaxed">Brokers inflate transport prices, leaving farmers with minimal profits and drivers with unfair wages.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-gray-100 text-gray-600 rounded-xl flex items-center justify-center text-2xl mb-6">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Empty Return Trips</h4>
                    <p class="text-gray-600 leading-relaxed">Transporters often drive back empty after a delivery. This wastes fuel and doubles the cost of logistics.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="benefits" class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-24">
                <div>
                    <h2 class="text-brand-600 font-semibold tracking-wide uppercase text-sm mb-2">For Farmers</h2>
                    <h3 class="text-3xl md:text-4xl font-heading font-bold text-gray-900 mb-6">Get your produce to market fresh and on time.</h3>
                    <ul class="space-y-6">
                        <li class="flex gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-brand-100 text-brand-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Instant Connections</h4>
                                <p class="text-gray-600 mt-1">Post a request and get matched with available drivers in your county within minutes.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-brand-100 text-brand-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-shield-check"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Verified Drivers Only</h4>
                                <p class="text-gray-600 mt-1">Every transporter is vetted with ID and logbook checks so your cargo is always safe.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-brand-100 text-brand-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Transparent Pricing</h4>
                                <p class="text-gray-600 mt-1">No hidden broker fees. Negotiate directly with the driver based on accurate mileage.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="relative">
                    <img src="https://images.pexels.com/photos/2255938/pexels-photo-2255938.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Happy farmer in field" class="rounded-2xl shadow-xl object-cover h-[450px] w-full">
                    <div class="absolute inset-0 border-2 border-brand-500 rounded-2xl transform translate-x-4 translate-y-4 -z-10"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center flex-col-reverse lg:flex-row-reverse">
                <div>
                    <h2 class="text-blue-600 font-semibold tracking-wide uppercase text-sm mb-2">For Transporters</h2>
                    <h3 class="text-3xl md:text-4xl font-heading font-bold text-gray-900 mb-6">Keep your truck moving and maximize earnings.</h3>
                    <ul class="space-y-6">
                        <li class="flex gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Find Jobs Anywhere</h4>
                                <p class="text-gray-600 mt-1">Browse a live feed of transport requests in your area. Perfect for finding return-trip loads.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-route"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Smart Routing</h4>
                                <p class="text-gray-600 mt-1">Get exact GPS pins for rural farms so you never get lost or waste fuel searching for pickup points.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Direct Payments</h4>
                                <p class="text-gray-600 mt-1">Deal directly with the farmer. Keep 100% of your negotiated transport fee.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="relative">
                    <img src="https://images.pexels.com/photos/2199293/pexels-photo-2199293.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Transport truck on road" class="rounded-2xl shadow-xl object-cover h-[450px] w-full">
                    <div class="absolute inset-0 border-2 border-blue-500 rounded-2xl transform -translate-x-4 translate-y-4 -z-10"></div>
                </div>
            </div>

        </div>
    </section>

    <section id="how-it-works" class="py-20 bg-brand-900 text-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h3 class="text-3xl md:text-4xl font-heading font-bold mb-4">How AgriMove Works</h3>
                <p class="text-brand-100 text-lg">A simple, three-step process to move your agricultural goods.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 relative">
                <div class="hidden md:block absolute top-12 left-[15%] right-[15%] h-0.5 bg-brand-700 z-0"></div>

                <div class="relative z-10 text-center">
                    <div class="w-24 h-24 mx-auto bg-brand-800 border-4 border-brand-500 rounded-full flex items-center justify-center text-3xl text-white mb-6 shadow-lg shadow-brand-900/50">
                        1
                    </div>
                    <h4 class="text-xl font-bold mb-3">Post a Request</h4>
                    <p class="text-brand-200">Farmers enter produce details, weight, and drop a GPS pin for pickup and delivery.</p>
                </div>
                
                <div class="relative z-10 text-center">
                    <div class="w-24 h-24 mx-auto bg-brand-800 border-4 border-brand-500 rounded-full flex items-center justify-center text-3xl text-white mb-6 shadow-lg shadow-brand-900/50">
                        2
                    </div>
                    <h4 class="text-xl font-bold mb-3">Transporter Accepts</h4>
                    <p class="text-brand-200">Local drivers view the request, check the route, and accept the job instantly.</p>
                </div>

                <div class="relative z-10 text-center">
                    <div class="w-24 h-24 mx-auto bg-brand-800 border-4 border-brand-500 rounded-full flex items-center justify-center text-3xl text-white mb-6 shadow-lg shadow-brand-900/50">
                        3
                    </div>
                    <h4 class="text-xl font-bold mb-3">Deliver & Earn</h4>
                    <p class="text-brand-200">The crop is transported safely. The buyer receives fresh goods, and the driver gets paid.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h3 class="text-3xl md:text-4xl font-heading font-bold text-gray-900 mb-4">Trusted by Kenyans</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 relative">
                    <i class="fa-solid fa-quote-left text-4xl text-brand-100 absolute top-6 left-6"></i>
                    <p class="text-gray-600 text-lg italic relative z-10 pl-8 mb-6">"Before AgriMove, I would wait days for a broker to find a truck for my cabbages. Now, I post a request and have a driver at my farm in Uasin Gishu the same morning."</p>
                    <div class="flex items-center gap-4 pl-8">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-700 font-bold">JM</div>
                        <div>
                            <h5 class="font-bold text-gray-900">John Mwaura</h5>
                            <p class="text-sm text-gray-500">Farmer, Uasin Gishu</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 relative">
                    <i class="fa-solid fa-quote-left text-4xl text-blue-50 absolute top-6 left-6"></i>
                    <p class="text-gray-600 text-lg italic relative z-10 pl-8 mb-6">"I used to drive my Canter back empty from Nairobi to Nakuru. Now I use AgriMove to find return trips. It has doubled my monthly income."</p>
                    <div class="flex items-center gap-4 pl-8">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-700 font-bold">DK</div>
                        <div>
                            <h5 class="font-bold text-gray-900">David Kiprono</h5>
                            <p class="text-sm text-gray-500">Transporter, Nakuru</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 relative overflow-hidden">
        <div class="absolute inset-0 bg-brand-600"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-brand-700 to-brand-500"></div>
        <svg class="absolute top-0 left-0 transform -translate-x-1/2 -translate-y-1/2 opacity-20 text-white w-96 h-96" fill="currentColor" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50"/></svg>
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <h2 class="text-3xl md:text-5xl font-heading font-bold text-white mb-6">Ready to revolutionize your transport?</h2>
            <p class="text-brand-100 text-lg md:text-xl mb-10">Join thousands of farmers and drivers building the future of agriculture logistics in Kenya.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="register.php" class="bg-white text-brand-700 hover:bg-gray-50 px-8 py-4 rounded-xl font-bold text-lg transition shadow-lg">
                    Create Free Account
                </a>
                <a href="login.php" class="bg-brand-800 text-white hover:bg-brand-900 border border-brand-700 px-8 py-4 rounded-xl font-bold text-lg transition shadow-lg">
                    Login to Dashboard
                </a>
            </div>
        </div>
    </section>

    <footer class="bg-gray-900 text-gray-300 py-12 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8 border-b border-gray-800 pb-8">
                
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 bg-brand-600 rounded flex items-center justify-center text-white">
                            <i class="fa-solid fa-truck-fast text-sm"></i>
                        </div>
                        <span class="font-heading font-bold text-xl text-white">AgriMove</span>
                    </div>
                    <p class="text-sm text-gray-400 leading-relaxed mb-4">
                        Smart transport for modern farming. Connecting Kenyan agriculture to markets efficiently.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fa-brands fa-facebook text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fa-brands fa-twitter text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fa-brands fa-instagram text-xl"></i></a>
                    </div>
                </div>

                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Platform</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="register.php?role=farmer" class="hover:text-brand-400 transition">Join as Farmer</a></li>
                        <li><a href="register.php?role=transporter" class="hover:text-brand-400 transition">Join as Transporter</a></li>
                        <li><a href="#how-it-works" class="hover:text-brand-400 transition">How it Works</a></li>
                        <li><a href="login.php" class="hover:text-brand-400 transition">Login</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Support</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-brand-400 transition">Help Center</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition">Safety Guidelines</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition">Privacy Policy</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-bold mb-4 uppercase text-sm tracking-wider">Contact Us</h4>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-location-dot mt-1 text-brand-500"></i>
                            <span>Nakuru Kabarak, Kenya</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <i class="fa-solid fa-phone text-brand-500"></i>
                            <span>+254 708 663 288</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <i class="fa-solid fa-envelope text-brand-500"></i>
                            <span>support@agrimove.co.ke</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row justify-between items-center text-xs text-gray-500">
                <p>&copy; <?php echo date('Y'); ?> AgriMove. All rights reserved.</p>
                <p class="mt-2 md:mt-0">Built for Kenyan Agriculture 🇰🇪</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Close mobile menu when clicking a link
        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', () => {
                menu.classList.add('hidden');
            });
        });

        // Navbar blur effect on scroll
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 20) {
                nav.classList.add('shadow-md');
                nav.classList.replace('bg-white', 'glass-nav');
            } else {
                nav.classList.remove('shadow-md');
                nav.classList.replace('glass-nav', 'bg-white');
            }
        });
    </script>
</body>
</html>