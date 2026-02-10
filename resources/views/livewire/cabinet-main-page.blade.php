<div class="overflow-hidden relative h-[100px] bg-blue-600 w-full flex items-center">
{{--    @if(!empty($rates))--}}
{{--        <div id="marquee-wrapper" class="w-full overflow-hidden">--}}
{{--            <div id="marquee" class="flex space-x-12">--}}
{{--                @foreach($rates as $coin => $price)--}}
{{--                    <div class="flex-shrink-0 px-6">--}}
{{--                        <span class="font-bold text-white text-xl">{{ strtoupper($coin) }}: </span>--}}
{{--                        <span class="font-bold text-white text-xl show-rate">${{ number_format($price, 2) }} </span>--}}
{{--                    </div>--}}
{{--                @endforeach--}}
{{--                <!-- Дублируем элементы, чтобы анимация была плавной и бесконечной -->--}}
{{--                @foreach($rates as $coin => $price)--}}
{{--                    <div class="flex-shrink-0 px-6">--}}
{{--                        <span class="font-bold text-white text-xl">{{ strtoupper($coin) }} :</span>--}}
{{--                        <span class="font-bold text-white text-xl show-rate">${{ number_format($price, 2) }}</span>--}}
{{--                    </div>--}}
{{--                @endforeach--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    @endif--}}

    <style>
        #marquee-wrapper {
            background-color: #006eff;
        }

        #marquee {
            display: flex;
            white-space: nowrap;
            animation: marquee 20s linear infinite;
        }

        .show-rate{
            margin-right: 10px;
        }

        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); } /* половина, так как дублируем элементы */
        }
    </style>

    <script>
        document.addEventListener('livewire:load', function () {
            Livewire.on('refreshRates', () => {
                // перезапускаем анимацию при обновлении данных
                const marquee = document.getElementById('marquee');
                if(marquee){
                    marquee.style.animation = 'none';
                    void marquee.offsetWidth; // перезапуск анимации
                    marquee.style.animation = '';
                }
            });
        });
    </script>
</div>
