import Image from 'next/image';
import phpStanLogo from './companies/phpstan.svg';
import bcastLogo from './companies/bcast.svg';
import myBuilderLogo from './companies/mybuilder.svg';
import neuralLoveLogo from './companies/neural-love.svg';
import enopteaLogo from './companies/enoptea.png';

export default function Companies() {
    return (
        <div className="bg-white py-24 sm:py-32">
            <div className="mx-auto max-w-7xl px-6 lg:px-8">
                <h2 className="text-center text-3xl font-black leading-8 text-gray-900">
                    Used in production by
                </h2>
                <div
                    className="mt-16 -mx-6 grid grid-cols-2 gap-0.5 overflow-hidden sm:mx-0 sm:rounded-2xl md:grid-cols-3">
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <Image
                            className="max-h-12 max-w-[10rem] w-full object-contain"
                            src={phpStanLogo}
                            alt="PhpStan"
                        />
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <Image
                            className="max-h-12 max-w-[10rem] w-full object-contain"
                            src={bcastLogo}
                            alt="bCast.fm"
                        />
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <Image
                            className="max-h-12 max-w-[10rem] w-full object-contain"
                            src={myBuilderLogo}
                            alt="MyBuilder"
                        />
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <Image
                            className="max-h-12 max-w-[10rem] w-full object-contain"
                            src={neuralLoveLogo}
                            alt="neural.love"
                        />
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <Image
                            className="max-h-12 max-w-[10rem] w-full object-contain"
                            src={enopteaLogo}
                            alt="Enoptea"
                        />
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <a href="https://www.phpjobs.app/" className="text-2xl font-bold">phpjobs.app</a>
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <a href="https://externals.io" className="text-2xl font-bold">externals.io</a>
                    </div>
                    <div className="bg-gray-400/5 p-8 sm:p-10 flex justify-center items-center">
                        <div><a href="https://github.com/brefphp/bref/issues/267">add your company</a></div>
                    </div>
                </div>
            </div>
        </div>
    );
}
